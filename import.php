<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");?>
<?require($_SERVER["DOCUMENT_ROOT"]."/podarki/import.class.php");?>

<?
$speed = 0.4;
//$file = file_get_contents($_SERVER['DOCUMENT_ROOT'].'/podarki/catalog.xml');
$file = file_get_contents('http://price.mixmarket.biz/uni/getxml.php?mid=1294939008&zid=1294957069');
$xml = new SimpleXMLElement($file); unset($file);
$step = !isset($_REQUEST['step'])?$step = 'start':$step = $_REQUEST['step'];
$page = !isset($_REQUEST['page'])?$page = 1:$page = $_REQUEST['page'];


if($step == 'start') {
    echo 'Старт<br>';
    unset(
        $_SESSION['cats'],
        $_SESSION['cats_uf_ids'],
        $_SESSION['update'],
        $_SESSION['offers'],
        $_SESSION['IDS'],
        $_SESSION['j'],
        $_SESSION['page'],
        $_SESSION['work']
    );

    foreach($xml->shop->offers->offer as $key => $offer) {
        $_SESSION['work'][] = (int)$offer->attributes()->id;
    }

    header('refresh: '.$speed.'; url=/podarki/import.php?step=category');
}

if($step == 'category') {
    $ctg = new Category();
    $ctg->GetFromSite();
    $ctg->GetFromXML($xml->shop->categories->category);
    $ctg->MakeRelation();

    header('refresh: '.$speed.'; url=/podarki/import.php?step=get_offers');
}

if($step == 'get_offers') {
    $ofs = new Offer();
    $ofs->GetAll();

    header('refresh: '.$speed.'; url=/podarki/import.php?step=offer');
}

if($step == 'offer') {
    $ofs = new Offer();
    $i = 0;
    $ofs->eco(count($_SESSION['work']).'<br>');
    foreach($xml->shop->offers->offer as $key => $offer) {
        $oif = (int)$offer->attributes()->id;
        if(!in_array($oif, $_SESSION['IDS'])) {
            $id = array_search($oif, $_SESSION['offers']);
            if(empty($id)) {
                $oid = $ofs->Add($offer);
                $ofs->eco('Добавлен товар: '.(string)$offer->name.'<br>');
            } else {
                $oid = $ofs->Update($id, $offer);
                $ofs->eco('Обновлен товар: '.(string)$offer->name.'<br>');
            }
            $_SESSION['IDS'][] = $oif;

            $i++;
            if(count($_SESSION['work']) < 1) {
                $step = 'final';
                break;
            }
            if($i > 35) {
                $page++;
                $step = 'offer&page='.$page;
                break;
            }
            array_shift($_SESSION['work']);
        }
    }

    if($step == 'final') {
        header('refresh: '.$speed.'; url=/podarki/import.php?step=final');
    } else {
        header('refresh: '.$speed.'; url=/podarki/import.php?step='.$step);
    }
}

if($step == 'final') {
    echo 'Работа завершена.';
}