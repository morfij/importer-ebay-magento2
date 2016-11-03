<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

const TABLE_NAME = 'table_name';
const OLD_FILE_NAME = 'source_file_name';
const NEW_FILE_NAME = 'new_file_name';

const SOURCE_FOLDER_NAME = 'source';
const NEW_FILES_FOLDER_NAME = 'import';
const IMAGES_FOLDER_NAME = 'new_images';

const HOST_NAME = 'localhost';
const DB_USER_NAME = 'user_name';
const DB_PASSWORD = 'password';
const DB_NAME = 'db_name';

include 'slugify.php';
include 'loader_img.php';

//removeDir('test_image');
//mkdir('test_image/', 0777, true);
//chmod('test_image/', 0777);

$db = new mysqli(HOST_NAME, DB_USER_NAME, DB_PASSWORD, DB_NAME);

//Parsing file
$file = SOURCE_FOLDER_NAME.'/'.OLD_FILE_NAME.'.csv';
$row = 1;

$sql = 'INSERT INTO `'.TABLE_NAME.'` VALUES';

if (($handle = fopen($file, "r")) !== FALSE) {
    while (($data = fgetcsv($handle, 100000, ",")) !== FALSE) {
        if ($row == 1) {
            //Create table
            $attrEbay = (explode(';',$data[0]));
            $createSql = 'CREATE TABLE IF NOT EXISTS `'.TABLE_NAME.'`( id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY, ';
            foreach($attrEbay as $i=>$title){
                if($title == '') $attrEbay[$i] = 'empty_title_'.$i;
                $attrEbaySql[$i] = '`'.$db->real_escape_string($attrEbay[$i]).'` TEXT';
                if((count($attrEbay) - 1) != $i) $attrEbaySql[$i].= ', ';
                $createSql .= $attrEbaySql[$i];
            }
            $createSql .= ');';
            if ($db->query($createSql) === TRUE) {
                echo "Table ".TABLE_NAME." created successfully";
                $db->query('TRUNCATE TABLE '.TABLE_NAME);
            } else {
                echo "Error creating table: " . $db->error;
            }
            $row++;
            break;
        }
    }
    //Add table titles
    while (($data = fgetcsv($handle, 100000, ";")) !== FALSE) {
        foreach ($data as $k => $v) {
            $data[$k] = $db->real_escape_string($v);
        }
        $sqlData = '"' . implode('","', $data) . '"';
        $query =  $sql . '(NULL, '.$sqlData .');';
        $res = $db->query($query);
        if (!$res) {
            echo $data[0].' ';
            printf("Errormessage: %s\n", $db->error);die;
        }
        echo $row."\r\n";
        $row++;
    }
    fclose($handle);
}

$importArray[] = [
    'store_view_code','attribute_set_code','product_type','product_websites','tax_class_name','website_id','sku',
    'name','description','short_description','weight','product_online','visibility','price','special_price','url_key',
    'qty','is_in_stock','additional_attributes','configurable_variations','configurable_variation_labels',
    'base_image','small_image','thumbnail_image','swatch_image'
];


//Simple products in configurable
$sqlSimple = 'SELECT * FROM '.TABLE_NAME.' WHERE `parent_sku` != "" ';
$resSimple = $db->query($sqlSimple);

$i=1;
while ($row = $resSimple->fetch_assoc()) {
    if($row['products_sku'] == '') continue;
    $importArray[$i] = ['','Default','simple','base','Taxable Goods','0'];
    $importArray[$i][] = $row['products_sku'];

    if(array_key_exists('Variation:Color', $row)) $color = $row['Variation:Color'];
    elseif (array_key_exists('custom:Color', $row)) $color = $row['custom:Color'];
    else $color = '';
    $last = '';
    if(array_key_exists('variation:Size', $row)){
        if($row['variation:Size'] == ''){
            if(array_key_exists('variation:Width', $row)) $last = $row['variation:Width'];
        } else{
            $last = $row['variation:Size'];
        }
    }else{
        if(array_key_exists('variation:Width', $row)) $last = $row['variation:Width'];
    }
    $name = htmlspecialchars_decode($row['products_name']) . ' ' . $color. ' ' . $last;

    $importArray[$i][] = str_replace('\'','`',iconv('utf-8', 'us-ascii//TRANSLIT', $name));
    $newDescription = str_replace('<h3>Description</h3>','',$row['products_description']);
    $newDescription = str_replace('<strong>Product Code : '.$row['parent_sku'].'</strong>', '', $newDescription);
    $newDescription = str_replace ('"','`',$newDescription);
    $importArray[$i][] = mb_convert_encoding($newDescription, 'utf-8');//str_replace (''\','\"',$newDescription);
    $importArray[$i][] = '';
    //image
    $importArray[$i][] = str_replace(',','.', $row['products_weight']);
    $importArray[$i][] = 1;
    $importArray[$i][] = 'Not Visible Individually';
    //price
    if($row['minimum_advertised_price'] != 0 || $row['minimum_advertised_price'] !=  ''){
        $importArray[$i][] = (float)str_replace(',','.', $row['minimum_advertised_price']); // prices?
    }else{
        $importArray[$i][] = (float)str_replace(',','.', $row['products_price']); // prices?
    }

    $importArray[$i][] = '';//str_replace(',','.', $row['amazon:floor_price']);
    $importArray[$i][] = slugify($name);
    $importArray[$i][] = 100; // qty?
    $importArray[$i][] = 1;
    //add attributes
    //add width
    $d4 ='';$width = '';
    $size = '';
    $attributes = explode('-', $row['variation_type']);
    foreach ($attributes as $key=>$attributeName){
        if(strripos($attributeName, 'Size')){
            if(array_key_exists('variation:'.addslashes($attributeName), $row)) $size = $row['variation:'.addslashes($attributeName)];
            if(array_key_exists('Variation:'.addslashes($attributeName), $row)) $size = $row['Variation:'.addslashes($attributeName)];

        }elseif($attributeName == 'Width'){
            $width = $row['variation:Width'];
        }
    }
    $importArray[$i][] = 'width ='.$width.',color='.$color.',size='.$size.',upc='.$row['upc'];

    $largeImageLabel = downloadImg($row['products_image_large'],IMAGES_FOLDER_NAME);
    $smallImageLabel = $largeImageLabel;
    if( $row['products_image_large'] != $row['products_image_small']) $smallImageLabel = downloadImg($row['products_image_small'],IMAGES_FOLDER_NAME);

    $importArray[$i][] = '';
    $importArray[$i][] = '';
    $importArray[$i][] = $largeImageLabel;
    $importArray[$i][] = $smallImageLabel;
    $importArray[$i][] = $smallImageLabel;
    $importArray[$i][] = $largeImageLabel;

    $i++;
    //,'qty','is_in_stock','additional_attributes'];
    //   echo '<pre>'.print_r($importArray,1);die;
}
//Configurable products
$sqlConf = 'SELECT * FROM '.TABLE_NAME.' WHERE `parent_sku` = "" AND `variation_type` != "" ';
$resConf = $db->query($sqlConf);

while ($row = $resConf->fetch_assoc()) {

    if($row['products_sku'] == '') continue;
    $importArray[$i] = ['','Default','configurable','base','Taxable Goods','0'];
    $importArray[$i][] = $row['products_sku'];
    if(array_key_exists('Variation:Color', $row)) $color = $row['Variation:Color'];
    elseif (array_key_exists('custom:Color', $row)) $color = $row['custom:Color'];
    else $color = '';
    $last = '';
    if(array_key_exists('variation:Size', $row)){
        if($row['variation:Size'] == ''){
            if(array_key_exists('variation:Width', $row)) $last = $row['variation:Width'];
        } else{
            $last = $row['variation:Size'];
        }
    }else{
        if(array_key_exists('variation:Width', $row)) $last = $row['variation:Width'];
    }
    $name = htmlspecialchars_decode($row['products_name']) . ' ' . $color. ' ' . $last;
    $importArray[$i][] = str_replace('\'','`',iconv('utf-8', 'us-ascii//TRANSLIT', $name));
    $newDescription = str_replace('<h3>Description</h3>','',$row['products_description']);
    $newDescription = str_replace('<strong>Product Code : '.$row['products_sku'].'</strong>', '', $newDescription);
    $newDescription = str_replace ('"','`',$newDescription);
    $importArray[$i][] = mb_convert_encoding($newDescription, 'utf-8');//str_replace ('"','\'',$newDescription);
    $importArray[$i][] = '';
    $importArray[$i][] = str_replace(',','.', $row['products_weight']);
    $importArray[$i][] = 1;
    $importArray[$i][] = 'Catalog, Search';
    //price
    if($row['minimum_advertised_price'] != 0 || $row['minimum_advertised_price'] !=  ''){
        $importArray[$i][] = str_replace(',','.', $row['minimum_advertised_price']);
    }else{
        $importArray[$i][] = str_replace(',','.', $row['products_price']);
    }

    $importArray[$i][] = '';//str_replace(',','.', $row['amazon:floor_price']);
    $importArray[$i][] = slugify($name);
    $importArray[$i][] = 0; // qty?
    $importArray[$i][] = 1;
    $importArray[$i][] = '';
    //configurable_variations
    $sqlSubSimple = 'SELECT * FROM '.TABLE_NAME.' WHERE `parent_sku` = "'.$row['products_sku'].'"';
    $resSubSimple = $db->query($sqlSubSimple);
    $j = 1;
    $confProd = '';

    $delSimpleColor =''; $colorSimple = '';
    $delSimpleSize = ''; $sizeSimple = '';
    $delSimpleWidth = ''; $widthSimple = '';
    $variationLabels = array();
    while($subSimpleRow = $resSubSimple->fetch_assoc()){
        $delimiter = '';
        if(count($subSimpleRow) != $j ) $delimiter = '|';


        $attributes = explode('-', $row['variation_type']);
        foreach ($attributes as $key=>$attributeName){

            if(strripos($attributeName, 'Size')){
                if(array_key_exists('variation:'.addslashes($attributeName), $subSimpleRow)) {
                    $sizeSimple = 'size='.$subSimpleRow['variation:'.addslashes($attributeName)];
                }
                if(array_key_exists('Variation:'.addslashes($attributeName), $subSimpleRow)) {
                    $sizeSimple = 'size='.$subSimpleRow['Variation:'.addslashes($attributeName)];
                }
                if($sizeSimple == ''){
                    $sizeSimple = 'size='.$subSimpleRow['variation:Size'];
                }
                $sizeSimple = str_replace(',','.',$sizeSimple);
                $variationLabels[] = 'size=Size';
                $delSimpleSize = ',';
            }elseif($attributeName == 'Width'){
                $widthSimple = 'width='.$subSimpleRow['variation:Width'];
                $delSimpleWidth = ',';
                $variationLabels[] = 'width=Width';
            }elseif ($attributeName == 'Color'){
                $subSimpleColor = '';
                if(array_key_exists('Variation:'.$attributeName,$subSimpleRow)) $subSimpleColor = $subSimpleRow['Variation:'.$attributeName];
                if(array_key_exists('custom:'.$attributeName, $subSimpleRow))  $subSimpleColor = $subSimpleRow['custom:'.$attributeName];
                $colorSimple = ',color='.$subSimpleColor;
                $delSimpleColor = ',';
                $variationLabels[] = 'color=Color';
            }
        }
        $confProd .= 'sku='.$subSimpleRow['products_sku'].$delSimpleSize.$sizeSimple.$delSimpleWidth.$widthSimple.$delSimpleColor.$colorSimple.$delimiter;
        $j++;
    }
    $importArray[$i][] = $confProd;
    $variationLabels = implode(',',array_unique($variationLabels));

    $importArray[$i][] = $variationLabels;
    $largeImageLabel = downloadImg($row['products_image_large'],IMAGES_FOLDER_NAME);
    $smallImageLabel = $largeImageLabel;
    if( $row['products_image_large'] != $row['products_image_small']) $smallImageLabel = downloadImg($row['products_image_small'],IMAGES_FOLDER_NAME);

    $importArray[$i][] = $largeImageLabel;
    $importArray[$i][] = $smallImageLabel;
    $importArray[$i][] = $smallImageLabel;
    $importArray[$i][] = $largeImageLabel;
    $i++;
}

//Simple alone
$sqlSimpleSimple = 'SELECT * FROM '.TABLE_NAME.' WHERE `parent_sku` = "" AND `variation_type` = "" ';
$resSimpleSimple = $db->query($sqlSimpleSimple);
while ($row = $resSimpleSimple->fetch_assoc()) {
    if($row['products_sku'] == '') continue;
    $importArray[$i] = ['','Default','simple','base','Taxable Goods','0'];
    $importArray[$i][] = $row['products_sku'];

    if(array_key_exists('Variation:Color', $row)) $color = $row['Variation:Color'];
    elseif (array_key_exists('custom:Color', $row)) $color = $row['custom:Color'];
    else $color = '';
    $last = '';
    if(array_key_exists('variation:Size', $row)){
        if($row['variation:Size'] == ''){
            if(array_key_exists('variation:Width', $row)) $last = $row['variation:Width'];
        } else{
            $last = $row['variation:Size'];
        }
    }else{
        if(array_key_exists('variation:Width', $row)) $last = $row['variation:Width'];
    }

    $name = htmlspecialchars_decode($row['products_name']) . ' ' . $color. ' ' . $last;

    $importArray[$i][] = str_replace('\'','`',iconv('utf-8', 'us-ascii//TRANSLIT', $name));
    $newDescription = str_replace('<h3>Description</h3>','',$row['products_description']);
    $newDescription = str_replace('<strong>Product Code : '.$row['products_sku'].'</strong>', '', $newDescription);
    $newDescription = str_replace ('"','`',$newDescription);
    $importArray[$i][] = mb_convert_encoding($newDescription, 'utf-8');//
    $importArray[$i][] = '';
    //image
    $importArray[$i][] = str_replace(',','.', $row['products_weight']);
    $importArray[$i][] = 1;
    $importArray[$i][] = 'Catalog, Search';
    //price
    if($row['minimum_advertised_price'] != 0 || $row['minimum_advertised_price'] !=  ''){
        $importArray[$i][] = str_replace(',','.', $row['minimum_advertised_price']); // prices?
    }else{
        $importArray[$i][] = str_replace(',','.', $row['products_price']); // prices?
    }

    $importArray[$i][] = '';//str_replace(',','.', $row['amazon:floor_price']);
    $importArray[$i][] = slugify($name);
    $importArray[$i][] = 100; // qty?
    $importArray[$i][] = 1;
    //add attributes
    //$importArray[$i][] = 'color='.$row['Variation:Color'].',size='.$row['variation:Size'].',upc='.$row['upc'];
    //}
    $color = '' ;
    $size = '';
    $width = '';
    $d1 = ''; $d2 = ''; $d3='';

    if(array_key_exists('custom:Color',$row)){
        if($row['custom:Color'] != '') $color = 'color='.$row['custom:Color']; $d1 = ',';
    }

    if(array_key_exists('custom:Size', $row)){
        if($row['custom:Size'] != '') $size = 'size='.$row['custom:Size']; $d2 = ',';
    }
    if(array_key_exists('variation:Width', $row)){
        if($row['variation:Width'] != '') $width = 'width='.$row['variation:Width']; $d3 = ',';
    }

    $importArray[$i][] = 'upc='.$row['upc'].$d1.$color.$d2.$size.$d3.$width;
    $importArray[$i][] = '';
    $importArray[$i][] = '';
    $largeImageLabel = downloadImg($row['products_image_large'],IMAGES_FOLDER_NAME);
    $smallImageLabel = $largeImageLabel;
    if( $row['products_image_large'] != $row['products_image_small']) $smallImageLabel = downloadImg($row['products_image_small'],IMAGES_FOLDER_NAME);

    $importArray[$i][] = $largeImageLabel;
    $importArray[$i][] = $smallImageLabel;
    $importArray[$i][] = $smallImageLabel;
    $importArray[$i][] = $largeImageLabel;
    $i++;
}
$fp = fopen(NEW_FILES_FOLDER_NAME.'/'.NEW_FILE_NAME.'.csv', 'w');
foreach ($importArray as $fields) {
    fputcsv($fp, $fields);
}

fclose($fp);