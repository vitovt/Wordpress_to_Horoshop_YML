<?php
/**
 * YML generator for Horoshop.ua
 */
//header("Content-Type: text/plain");

//Suppress notices
error_reporting(E_ALL ^ E_NOTICE);
set_time_limit(900);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$allowed_args = array(
        "x_baseurl:",
        "x_limit:",
        "x_product_id:",
        "x_cat_limit:",
        "x_simplecat:",
        "x_lang:",
        "x_pretty:",
        "x_baseurl:",
        "x_product_custom:", //todo
        "x_fix_utf:",
        "x_show_empty_aliases:",
);

if(php_sapi_name() == 'cli') {
    $arguments = getopt("",$allowed_args);

    //wrong or misspelled argumets check:
    $all_args = array();
    foreach($argv as $temp) {
        $all_args[] = preg_replace('#--(.*)=.*#i', '$1:', $temp);
    }
    array_shift($all_args); //remove first item = php filename
    // var_dump($all_args);
    $bad_args = array_diff($all_args, $allowed_args);
    if(!empty($bad_args)) {
        echo 'Wrong or misspelled arguments:' . PHP_EOL;
        var_dump($bad_args);
        die('Fix your input!');
    }


    $XML_KEY=true;
    $base_url = 'https://horoshop.ua';
} else {
    $arguments = $_REQUEST;
    if(isset($_GET['XML_KEY'])) {$XML_KEY=true;} else {$XML_KEY=false;}
    $base_url = sprintf(
        "%s://%s",
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
        $_SERVER['SERVER_NAME']
    );
    if(isset($_GET['web_admin'])) {
        echo '<h1>Export options</h1>';
        exit;
    }
}
 /*
 * Making XML price in Horoshop format (https://horoshop.ua)
 * Class YGenerator
 */
class YGenerator {

    private $db;
    private $pdo;
    private $tp;

    public $labels = array();

    private $term_catalog = 'product_cat';
    //private $term_catalog = 'pa_jeffekty-chaja-1';

    public  $base_url;          //URL сайта, базовый для ссылок и картинок
    public $x_pretty = 1; //Красивое форматирование XML - Человекочитабельный формат или в одну строку
    private $x_lang = 0;  //Язык по умолчанчию (0, чтобы проигнорировать)
    private $x_limit = 10; //Ограничение в количестве товаров (для отладки, чтоб быстрее работало)
    private $x_cat_limit = 0; //Ограничение в количестве категорий (для отладки, чтоб быстрее работало)
    private $x_simplecat = 0; //Выводить категории в упрощеном виде в одну строку (стандартный YML формат)
    private $x_product_id; //id конкретного товара (для дебага). TODO: Перечисление через запятую товаров, если нужны конкретные id шники
    private $x_fix_utf = 1; //автоматично виправляти биті UTF символи
    private $x_show_empty_aliases = 1; //В разі відсутності alias в базі виводити типу index.php?route=product/category&path=ID
    public $x_multilang_tags = 0; //Вывести все теги как мультиязычные. Например: description_uk, description_ru вместо <description lang=1> 
    public $x_multilang_tags_no_default = 0; //Вывести основной тег без мультиязычной приставки. Например: description, description_ru вместо <description lang=1> 

private function siteURL() {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || 
    $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'];
    return $protocol.$domainName;
}

public function console_log( $data ){
  echo '<script>';
  echo 'console.log('. json_encode( $data ) .')';
  echo '</script>';
}

    public function __construct($arguments) {
        //?? is php7+ dependend function. May fail on ancient php5.x installations
        //in this case should be rewriten to isset() function

        foreach($arguments as $key=>$value) {
            $this->$key = (int)$value;
        }

        //Подключить конфиг
        /** Define ABSPATH as this file's directory */
        if ( ! defined( 'ABSPATH' ) ) {
                define( 'ABSPATH', __DIR__ . '/' );
        }

        /*
        * If wp-config.php exists in the WordPress root, or if it exists in the root and wp-settings.php
        * doesn't, load wp-config.php. The secondary check for wp-settings.php has the added benefit
        * of avoiding cases where the current directory is a nested installation, e.g. / is WordPress(a)
        * and /blog/ is WordPress(b).
        *
        * If neither set of conditions is true, initiate loading the setup process.
        */
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {

                /** The config file resides in ABSPATH */
                require_once ABSPATH . 'wp-config.php';

        } elseif ( @file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! @file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {

                /** The config file resides one level above ABSPATH but is not part of another installation */
                require_once dirname( ABSPATH ) . '/wp-config.php';

        } else {
            die('wrong config file location!');
        }
        //Открыть базу
        // mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        // $this->db = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        // if ($this->db->connect_error) {
        //     die('MySQL Connect ERROR # (' . $mysqli->connect_errno . ') '
        //                     . $mysqli->connect_error);
        // }

        $host = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($host, DB_USER, DB_PASSWORD, $options);
        } catch (\PDOException $e) {
            throw new \PDOException($e->getMessage(), (int)$e->getCode());
        }

        // $this->db->set_charset("utf8mb4");
        $this->tp = $table_prefix;
    }

        /**
     * @param $str
     * @return mixed
     */
    private function cutExtraCharacters($str){
    //unused function. Returns the same as input. May be useful in future for attributes.
        $cyr = [
            ' кг', ' л', ' Вт', ' куб. м/ч', ' см', ' дБ'
        ];

        //$str = str_replace($cyr, '', $str);
        return $str;
    }

function get_html_split_regex() {
    static $regex;
 
    if ( ! isset( $regex ) ) {
        // phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
        $comments =
            '!'             // Start of comment, after the <.
            . '(?:'         // Unroll the loop: Consume everything until --> is found.
            .     '-(?!->)' // Dash not followed by end of comment.
            .     '[^\-]*+' // Consume non-dashes.
            . ')*+'         // Loop possessively.
            . '(?:-->)?';   // End of comment. If not found, match all input.
 
        $cdata =
            '!\[CDATA\['    // Start of comment, after the <.
            . '[^\]]*+'     // Consume non-].
            . '(?:'         // Unroll the loop: Consume everything until ]]> is found.
            .     '](?!]>)' // One ] not followed by end of comment.
            .     '[^\]]*+' // Consume non-].
            . ')*+'         // Loop possessively.
            . '(?:]]>)?';   // End of comment. If not found, match all input.
 
        $escaped =
            '(?='             // Is the element escaped?
            .    '!--'
            . '|'
            .    '!\[CDATA\['
            . ')'
            . '(?(?=!-)'      // If yes, which type?
            .     $comments
            . '|'
            .     $cdata
            . ')';
 
        $regex =
            '/('                // Capture the entire match.
            .     '<'           // Find start of element.
            .     '(?'          // Conditional expression follows.
            .         $escaped  // Find end of escaped element.
            .     '|'           // ...else...
            .         '[^>]*>?' // Find end of normal element.
            .     ')'
            . ')/';
        // phpcs:enable
    }
 
    return $regex;
}

function wp_html_split( $input ) {
    return preg_split( $this->get_html_split_regex(), $input, -1, PREG_SPLIT_DELIM_CAPTURE );
}

function wp_replace_in_html_tags( $haystack, $replace_pairs ) {
    // Find all elements.
    $textarr = $this->wp_html_split( $haystack );
    $changed = false;
 
    // Optimize when searching for one item.
    if ( 1 === count( $replace_pairs ) ) {
        // Extract $needle and $replace.
        foreach ( $replace_pairs as $needle => $replace ) {
        }
 
        // Loop through delimiters (elements) only.
        for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
            if ( false !== strpos( $textarr[ $i ], $needle ) ) {
                $textarr[ $i ] = str_replace( $needle, $replace, $textarr[ $i ] );
                $changed       = true;
            }
        }
    } else {
        // Extract all $needles.
        $needles = array_keys( $replace_pairs );
 
        // Loop through delimiters (elements) only.
        for ( $i = 1, $c = count( $textarr ); $i < $c; $i += 2 ) {
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $textarr[ $i ], $needle ) ) {
                    $textarr[ $i ] = strtr( $textarr[ $i ], $replace_pairs );
                    $changed       = true;
                    // After one strtr() break out of the foreach loop and look at next element.
                    break;
                }
            }
        }
    }
 
    if ( $changed ) {
        $haystack = implode( $textarr );
    }
 
    return $haystack;
}

function _autop_newline_preservation_helper( $matches ) {
    return str_replace( "\n", '<WPPreserveNewline />', $matches[0] );
}
    //https://developer.wordpress.org/reference/functions/wpautop/
    public function wpautop( $text, $br = true ) {
        $pre_tags = array();

        if ( trim( $text ) === '' ) {
            return '';
        }

        // Just to make things a little easier, pad the end.
        $text = $text . "\n";

        /*
        * Pre tags shouldn't be touched by autop.
        * Replace pre tags with placeholders and bring them back after autop.
        */
        if ( strpos( $text, '<pre' ) !== false ) {
            $text_parts = explode( '</pre>', $text );
            $last_part  = array_pop( $text_parts );
            $text       = '';
            $i          = 0;

            foreach ( $text_parts as $text_part ) {
                $start = strpos( $text_part, '<pre' );

                // Malformed HTML?
                if ( false === $start ) {
                    $text .= $text_part;
                    continue;
                }

                $name              = "<pre wp-pre-tag-$i></pre>";
                $pre_tags[ $name ] = substr( $text_part, $start ) . '</pre>';

                $text .= substr( $text_part, 0, $start ) . $name;
                $i++;
            }

            $text .= $last_part;
        }
        // Change multiple <br>'s into two line breaks, which will turn into paragraphs.
        $text = preg_replace( '|<br\s*/?>\s*<br\s*/?>|', "\n\n", $text );

        $allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';

        // Add a double line break above block-level opening tags.
        $text = preg_replace( '!(<' . $allblocks . '[\s/>])!', "\n\n$1", $text );

        // Add a double line break below block-level closing tags.
        $text = preg_replace( '!(</' . $allblocks . '>)!', "$1\n\n", $text );

        // Add a double line break after hr tags, which are self closing.
        $text = preg_replace( '!(<hr\s*?/?>)!', "$1\n\n", $text );

        // Standardize newline characters to "\n".
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );

        // Find newlines in all elements and add placeholders.
        $text = $this->wp_replace_in_html_tags( $text, array( "\n" => ' <!-- wpnl --> ' ) );

        // Collapse line breaks before and after <option> elements so they don't get autop'd.
        if ( strpos( $text, '<option' ) !== false ) {
            $text = preg_replace( '|\s*<option|', '<option', $text );
            $text = preg_replace( '|</option>\s*|', '</option>', $text );
        }

        /*
        * Collapse line breaks inside <object> elements, before <param> and <embed> elements
        * so they don't get autop'd.
        */
        if ( strpos( $text, '</object>' ) !== false ) {
            $text = preg_replace( '|(<object[^>]*>)\s*|', '$1', $text );
            $text = preg_replace( '|\s*</object>|', '</object>', $text );
            $text = preg_replace( '%\s*(</?(?:param|embed)[^>]*>)\s*%', '$1', $text );
        }

        /*
        * Collapse line breaks inside <audio> and <video> elements,
        * before and after <source> and <track> elements.
        */
        if ( strpos( $text, '<source' ) !== false || strpos( $text, '<track' ) !== false ) {
            $text = preg_replace( '%([<\[](?:audio|video)[^>\]]*[>\]])\s*%', '$1', $text );
            $text = preg_replace( '%\s*([<\[]/(?:audio|video)[>\]])%', '$1', $text );
            $text = preg_replace( '%\s*(<(?:source|track)[^>]*>)\s*%', '$1', $text );
        }

        // Collapse line breaks before and after <figcaption> elements.
        if ( strpos( $text, '<figcaption' ) !== false ) {
            $text = preg_replace( '|\s*(<figcaption[^>]*>)|', '$1', $text );
            $text = preg_replace( '|</figcaption>\s*|', '</figcaption>', $text );
        }

        // Remove more than two contiguous line breaks.
        $text = preg_replace( "/\n\n+/", "\n\n", $text );

        // Split up the contents into an array of strings, separated by double line breaks.
        $paragraphs = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );

        // Reset $text prior to rebuilding.
        $text = '';

        // Rebuild the content as a string, wrapping every bit with a <p>.
        foreach ( $paragraphs as $paragraph ) {
            $text .= '<p>' . trim( $paragraph, "\n" ) . "</p>\n";
        }

        // Under certain strange conditions it could create a P of entirely whitespace.
        $text = preg_replace( '|<p>\s*</p>|', '', $text );

        // Add a closing <p> inside <div>, <address>, or <form> tag if missing.
        $text = preg_replace( '!<p>([^<]+)</(div|address|form)>!', '<p>$1</p></$2>', $text );

        // If an opening or closing block element tag is wrapped in a <p>, unwrap it.
        $text = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $text );

        // In some cases <li> may get wrapped in <p>, fix them.
        $text = preg_replace( '|<p>(<li.+?)</p>|', '$1', $text );

        // If a <blockquote> is wrapped with a <p>, move it inside the <blockquote>.
        $text = preg_replace( '|<p><blockquote([^>]*)>|i', '<blockquote$1><p>', $text );
        $text = str_replace( '</blockquote></p>', '</p></blockquote>', $text );

        // If an opening or closing block element tag is preceded by an opening <p> tag, remove it.
        $text = preg_replace( '!<p>\s*(</?' . $allblocks . '[^>]*>)!', '$1', $text );

        // If an opening or closing block element tag is followed by a closing <p> tag, remove it.
        $text = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*</p>!', '$1', $text );

        // Optionally insert line breaks.
        if ( $br ) {
            // Replace newlines that shouldn't be touched with a placeholder.
            $text = preg_replace_callback( '/<(script|style|svg).*?<\/\\1>/s', [$this, '_autop_newline_preservation_helper'], $text );

            // Normalize <br>
            $text = str_replace( array( '<br>', '<br/>' ), '<br />', $text );

            // Replace any new line characters that aren't preceded by a <br /> with a <br />.
            $text = preg_replace( '|(?<!<br />)\s*\n|', "<br />\n", $text );

            // Replace newline placeholders with newlines.
            $text = str_replace( '<WPPreserveNewline />', "\n", $text );
        }

        // If a <br /> tag is after an opening or closing block tag, remove it.
        $text = preg_replace( '!(</?' . $allblocks . '[^>]*>)\s*<br />!', '$1', $text );

        // If a <br /> tag is before a subset of opening or closing block tags, remove it.
        $text = preg_replace( '!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $text );
        $text = preg_replace( "|\n</p>$|", '</p>', $text );

        // Replace placeholder <pre> tags with their original content.
        if ( ! empty( $pre_tags ) ) {
            $text = str_replace( array_keys( $pre_tags ), array_values( $pre_tags ), $text );
        }

        // Restore newlines in all elements.
        if ( false !== strpos( $text, '<!-- wpnl -->' ) ) {
            $text = str_replace( array( ' <!-- wpnl --> ', '<!-- wpnl -->' ), "\n", $text );
        }

        return $text;
    }

    private function get_taxonomy_value ($taxonomy_name, $product_id) {
        $sql = <<< SQL
        SELECT     taxonomy, name
        FROM      {$this->tp}term_relationships
        LEFT JOIN {$this->tp}term_taxonomy
        ON        {$this->tp}term_relationships.term_taxonomy_id = {$this->tp}term_taxonomy.term_taxonomy_id
        LEFT JOIN {$this->tp}terms
        ON        {$this->tp}terms.term_id = {$this->tp}term_taxonomy.term_id
        WHERE     taxonomy IN (?)
        AND       object_id = ?
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $opts = array($taxonomy_name, $product_id);
        $stmt->execute($opts);
        $values = $stmt->fetch();

        return $values['name'];
    }

    private function get_attribute_value ($attribute_name, $variation_id) {
        $sql = <<< SQL
        SELECT     meta_value
        FROM      {$this->tp}postmeta
        WHERE     meta_key IN (?)
        AND       post_id = ?
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $opts = array('attribute_' . $attribute_name, $variation_id);
        $stmt->execute($opts);
        $values = $stmt->fetch();

        return $values['meta_value'];
    }

    private function get_attribute_labels () {
        $labels = array();

        $sql = <<< SQL
        SELECT     attribute_label, attribute_name
        FROM      {$this->tp}woocommerce_attribute_taxonomies
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $opts = array();
        $stmt->execute($opts);
        while ($value = $stmt->fetch()) {
            $labels[ 'pa_' . $value['attribute_name']] = $value['attribute_label'];
        }

        return $labels;
    }
        private function getCategoryOfProduct($productId) {
            $taxonomy_name = $this->term_catalog;
            $values = array();

            $sql = <<< SQL
            SELECT taxonomy, name, {$this->tp}terms.term_id FROM {$this->tp}term_relationships
                    LEFT JOIN {$this->tp}term_taxonomy
                        ON {$this->tp}term_relationships.term_taxonomy_id = {$this->tp}term_taxonomy.term_taxonomy_id
                    LEFT JOIN {$this->tp}terms
                        ON {$this->tp}terms.term_id = {$this->tp}term_taxonomy.term_id
                    WHERE taxonomy IN (?) AND object_id = ?
            SQL;
            $stmt = $this->pdo->prepare($sql);
            $opts = array($taxonomy_name, $productId);
            $stmt->execute($opts);
            while ($value = $stmt->fetch()) {
                $values[] = $value['term_id'];
            }
            return $values;
        }

    /**
     * Building YML
     * @return SimpleXMLElement
     */
    public function getYml() {

        $xml = new SimpleXMLExtended("<?xml version=\"1.0\" encoding=\"UTF-8\"?><hcatalog/>");
        $dt = date("Y-m-d");
        $tm = date("H:i");
        $xml->addAttribute("date", $dt . ' ' . $tm);


        $shop = $xml->addChild('hshop');
        $shop->addChild('name', "Horoshop-Export");
        $shop->addChild('company', "Horoshop");
        $shop->addChild('url', "https://www.horoshop.ua/");
        $shop->addChild('version', "1.0.1");
        $sql = "SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        // #### Categories Section ####
        $categories = $shop->addChild('categories');
        $sql = "SELECT *, {$this->tp}terms.term_id AS tid
FROM {$this->tp}term_relationships
LEFT JOIN {$this->tp}term_taxonomy
   ON ({$this->tp}term_relationships.term_taxonomy_id = {$this->tp}term_taxonomy.term_taxonomy_id)
LEFT JOIN {$this->tp}terms on {$this->tp}terms.term_id = {$this->tp}term_taxonomy.term_id
WHERE {$this->tp}term_taxonomy.taxonomy = '{$this->term_catalog}'
GROUP BY {$this->tp}term_taxonomy.term_id";
        // $sql .= ' AND status = 1';
        $sql .= ' ORDER BY `tid`';
        if($this->x_cat_limit) { $sql .= " LIMIT $this->x_cat_limit"; }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        while ($row2 = $stmt->fetch()) {
            if ($this->x_simplecat) {
                $category = $categories->addChild('category', htmlspecialchars($row2['name']));
                $category->addAttribute("id", $row2['term_id']);
                // $category->addAttribute("langid", $row['language_id']);
                $parentId = $row2['parent'];
                if ($parentId != 0) {
                    $category->addAttribute("parentId", $parentId);
                }
            } else {
            $category = $categories->addChild('category'); //echo $row['name'] . PHP_EOL;
            $category->addAttribute("id", $row2['term_id']);
            $parentId = $row2['parent'];
            if ($parentId != 0) {
                $category->addAttribute("parentId", $parentId);
            }
            // $category->addChild("sort_order", $row2['sort_order']);
            // $category->addChild("top", $row2['top']);
            // $category->addChild("image", $this->base_url . $row2['image']);
            // $category->addChild("url", $this->base_url . '/index.php?route=product/category&amp;category_id=' . $row2['category_id']);
            $category->addChild("url", $row2['slug']);

                $language = $category->addChild("language");
                // $language->addAttribute("id", $row['language_id']);
                $language->addAttribute("id",1 );
                $language->addChild("name", htmlspecialchars($row2['name']));
                $language->addChildWithCDATA('seo_description', html_entity_decode($row2['description']));
                // $language->addChild("meta_title", htmlspecialchars($row['meta_title']));
                // $language->addChild("meta_keyword", htmlspecialchars($row['meta_keyword']));
                // $language->addChild("meta_description", htmlspecialchars($row['meta_description']));
                // if(array_key_exists('h1', $row)) { $language->addChild("h1", htmlspecialchars($row['h1'])); }
            } //fullcat (!simplecat)
        }

        //#### End Categories Section ####

        $offers = $shop->addChild('offers');

        $this->labels = $this->get_attribute_labels();

        $opts = array();

        $sql = "SELECT
  {$this->tp}posts.ID AS product_id,
  {$this->tp}posts.post_title AS name,
  {$this->tp}posts.post_content AS description,
  {$this->tp}posts.post_name AS alias,
  {$this->tp}posts.guid AS guid,
  {$this->tp}postmeta1.meta_value AS article,
  {$this->tp}postmeta2.meta_value AS price,
  {$this->tp}postmeta3.meta_value AS quantity,
  {$this->tp}postmeta4.meta_value AS stock,
  {$this->tp}postmeta5.meta_value AS product_attributes_raw,
  {$this->tp}postmeta6.meta_value AS default_attributes_raw,
  {$this->tp}postmeta7.meta_value AS regular_price,
  {$this->tp}postmeta8.meta_value AS sale_price
FROM {$this->tp}posts
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta1
  ON {$this->tp}postmeta1.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta1.meta_key = '_sku'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta2
  ON {$this->tp}postmeta2.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta2.meta_key = '_price'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta3
  ON {$this->tp}postmeta3.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta3.meta_key = '_stock'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta4
  ON {$this->tp}postmeta4.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta4.meta_key = '_stock_status'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta5
  ON {$this->tp}postmeta5.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta5.meta_key = '_product_attributes'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta6
  ON {$this->tp}postmeta6.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta6.meta_key = '_default_attributes'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta7
  ON {$this->tp}postmeta7.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta7.meta_key = '_regular_price'
LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta8
  ON {$this->tp}postmeta8.post_id = {$this->tp}posts.ID
  AND {$this->tp}postmeta8.meta_key = '_sale_price'
WHERE {$this->tp}posts.post_type = 'product'
 AND {$this->tp}posts.post_status = 'publish'";

        if($this->x_product_id) {
            $sql .= " AND ID = ?";
            $opts[] = $this->x_product_id;
        }

        //GROUP BY {$this->tp}posts.ID
        //$sql .= " ORDER BY {$this->tp}posts.ID ASC ";

        // $sql .= " GROUP BY {$this->tp}posts.ID ";
        if($this->x_limit) {
            $sql .= ' LIMIT ?';
            $opts[] = $this->x_limit;
        }
        // $result = $this->db->query($sql);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($opts);

        $sql2 = "SELECT {$this->tp}posts.*,
        {$this->tp}postmeta2.meta_value AS price,
        {$this->tp}postmeta3.meta_value AS quantity,
        {$this->tp}postmeta4.meta_value AS stock
        FROM {$this->tp}posts
        LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta2
          ON {$this->tp}postmeta2.post_id = {$this->tp}posts.ID
          AND {$this->tp}postmeta2.meta_key = '_price'
        LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta3
            ON {$this->tp}postmeta3.post_id = {$this->tp}posts.ID
            AND {$this->tp}postmeta3.meta_key = '_stock'
        LEFT JOIN {$this->tp}postmeta {$this->tp}postmeta4
            ON {$this->tp}postmeta4.post_id = {$this->tp}posts.ID
            AND {$this->tp}postmeta4.meta_key = '_stock_status'
        WHERE {$this->tp}posts.post_type = 'product_variation'
        AND post_parent = ?";

        $stmt2 = $this->pdo->prepare($sql2);

        $sql3 = "SELECT guid FROM {$this->tp}posts WHERE post_type='attachment' AND post_parent=?";
        $stmt3 = $this->pdo->prepare($sql3);

        while ($product = $stmt->fetch()) {
            $ID = $product['product_id'];

            $opts2 = array($ID);
            $stmt2->execute($opts2);
            $variation_count = $stmt2->rowCount();

            $opts3 = array($ID);
            $stmt3->execute($opts3);
            $pictures = $stmt3->fetchAll();
            $categories = $this->getCategoryOfProduct($ID);
            $offer_id = $product['product_id'];

            $product['description'] = $this->wpautop($product['description']);
            $product['description_short'] = $this->wpautop($product['description_short']);

            if($variation_count) {
                while ($variation = $stmt2->fetch()) {
                    $variation_id = $variation['ID'];
                    $offer = $offers->addChild('offer');
                    $offer->addAttribute("group_id", $offer_id);
                    $offer->addAttribute("id", $variation_id);
                    unset($product['product_id']);
                    $product['price'] = $variation['price'];
                    $product['stock'] = $variation['stock'];

                    // $product['name'] = $variation['post_title'];
                    //$product['alias'] = $variation['post_name'];

                    // $offer_name = $product['Product'];
                    // $offer->addChild('name', $offer_name);
                    foreach ($categories as $category) {
                        $offer->addChild('categoryId', $category);
                    }
                    foreach ($product as $key=>$value) {
                        $this->addChildWithLangOptions($offer, $key, $value, 0, $cdata = 'auto');
                    }
                    foreach ($pictures as $picture) {
                        $offer->addChild('picture', $picture['guid']);
                    }
                    $listAttributes = unserialize($product['product_attributes_raw']);
                        ### Adding attributes
                        foreach ($listAttributes as $key => $value) {
                            // $valueAttribute = trim($value['value']);
                            if($value['is_variation']) {
                                //$valueAttribute = $this->get_taxonomy_value($value['name'], $variation_id);
                                $valueAttribute = $this->get_attribute_value($value['name'], $variation_id);
                                $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                                $param = $this->addChildWithLangOptions($offer, 'param', $valueAttribute, 'ru', 0);
                                $param->addAttribute('type', 'modification');
                            } else {
                                $valueAttribute = $this->get_taxonomy_value($value['name'], $offer_id);
                                $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                                $param = $this->addChildWithLangOptions($offer, 'param', $valueAttribute, 'ru', 0);
                                $param->addAttribute('type', 'attribute');
                            }
                            $param->addAttribute('id', $value['name']);
                            $param->addAttribute('name', $this->labels[$value['name']]);
                            // $param->addAttribute('id', $listAttributes[$i]['attribute_id']);
                        }
                    }
            } else {

                $offer = $offers->addChild('offer');
                $offer->addAttribute("id", $offer_id);
                unset($product['product_id']);
                // $offer_name = $product['Product'];
                // $offer->addChild('name', $offer_name);
                foreach ($categories as $category) {
                    $offer->addChild('categoryId', $category);
                }
                foreach ($product as $key=>$value) {
                    $this->addChildWithLangOptions($offer, $key, $value, 0, $cdata = 'auto');
                }
                foreach ($pictures as $picture) {
                    $offer->addChild('picture', $picture['guid']);
                }
                $listAttributes = unserialize($product['product_attributes_raw']);
                    ### Adding attributes
                    foreach ($listAttributes as $key=> $value) {
                        // $valueAttribute = trim($value['value']);
                        $valueAttribute = $this->get_taxonomy_value($value['name'], $offer_id);
                        $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                        $param = $this->addChildWithLangOptions($offer, 'param', $valueAttribute, 'ru', 0);
                        if($value['is_variation']) {
                                $valueAttribute = $this->get_taxonomy_value($value['name'], $variation_id);
                                $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                                $param = $this->addChildWithLangOptions($offer, 'param', $valueAttribute, 'ru', 0);
                                $param->addAttribute('type', 'modification');
                            } else {
                                $valueAttribute = $this->get_taxonomy_value($value['name'], $offer_id);
                                $valueAttribute = $this->cutExtraCharacters($valueAttribute);
                                $param = $this->addChildWithLangOptions($offer, 'param', $valueAttribute, 'ru', 0);
                                $param->addAttribute('type', 'attribute');
                        }
                        $param->addAttribute('id', $value['name']);
                        $param->addAttribute('name', $this->labels[$value['name']]);
                        // $param->addAttribute('id', $listAttributes[$i]['attribute_id']);
                    }
            }
        }

        return $xml;

    }
    public function addChildWithLangOptions($offer, $name, $value = NULL, $langid = 0, $cdata = 0) {
    //echo $value . PHP_EOL;
    //$value = htmlspecialchars($value);
    //$search = array('"', '&');
    //$replace= array('&quot;', '&amp;');
    //str_replace($search, $replace, $value);
    //if($name == 'description') { $value = $this->fix_latin1_mangled_with_utf8_maybe_hopefully_most_of_the_time($value); }
    //ремонтуємо калічний опис товарів
    //згідно з: https://webcollab.sourceforge.io/unicode.html (Character Validation)
    if($this->x_fix_utf) {
    $value = trim($value);
    $value = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
    '|(?<=^|[\x00-\x7F])[\x80-\xBF]+'.
    '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
    '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
    '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/',
    '☆', $value );
    $value = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
    '|\xED[\xA0-\xBF][\x80-\xBF]/S','❉', $value );
        }

    if($this->x_multilang_tags) {
        if(!($this->x_multilang_tags_no_default && $this->default_language_id == $langid)) {
            $name .= '_' . str_replace(array(' ', '-'), '_', trim($this->languages[$langid]['code']));
        }
    }
    if($cdata == 'auto') {
        if ( preg_match('/[a-z_\-0-9]/i', $value) || $value == '') {
            $cdata = 0;
        } else {
            $cdata = 1;
        }
    }
        if($cdata) {
            $new_child = $offer->addChildWithCDATA($name, $value);
        } else {
            $new_child = $offer->addChild($name, $value);
        }
        if ($langid) {
            $new_child->addAttribute('langid', $langid);
        }

    return $new_child;
    }
}

// http://coffeerings.posterous.com/php-simplexml-and-cdata
class SimpleXMLExtended extends SimpleXMLElement {
  public function addCData($cdata_text) {
    $node = dom_import_simplexml($this);
    $no   = $node->ownerDocument;
    $node->appendChild($no->createCDATASection($cdata_text));
  }

   /**
   * Adds a child with $value inside CDATA
   * @param unknown $name
   * @param unknown $value
   */
  public function addChildWithCDATA($name, $value = NULL) {
    $new_child = $this->addChild($name);

    if ($new_child !== NULL) {
      $node = dom_import_simplexml($new_child);
      $no   = $node->ownerDocument;
      $node->appendChild($no->createCDATASection($value));
    }

    return $new_child;
  }
}

if($XML_KEY) {
    date_default_timezone_set('Europe/Kiev');
    $yGenerator = new YGenerator($arguments);
    if(isset($yGenerator->x_baseurl)) {
        $yGenerator->base_url = $arguments['x_baseurl'];
    } else {
        $yGenerator->base_url = $base_url;
    }

    $xml = $yGenerator->getYml();
    Header('Content-type: text/xml');

    if($yGenerator->x_pretty) {
      $doc = new DOMDocument();
      $doc->preserveWhiteSpace = false;
      $doc->formatOutput = true;
      $doc->loadXML($xml->asXML());
      echo $doc->saveXML();
    } else {
      print($xml->asXML());
    }

} else echo '-= access denied =-';

?>
