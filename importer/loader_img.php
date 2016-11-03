<?php
function downloadImg($url_files, $folderName){
    $nm = substr($url_files,strripos($url_files,'/'));
    if($name = strstr($nm, '?', true)) $nm = $name;

    if (preg_match("/http/",$url_files)){
        $ch = curl_init($url_files);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);
        $out = curl_exec($ch);
        $image_sv = $folderName.$nm;
        $img_sc = file_put_contents($image_sv, $out);
        echo $nm."\n";
        curl_close($ch);

    }
    return substr(urldecode($nm),1);
}