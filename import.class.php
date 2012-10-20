<?
class Category extends ImportConfig {

    /**
     * Получаем список всех категорий
     * @return array
     */
    function GetFromSite() {

        $bs = new CIBlockSection;
        $arSort = array('SORT'=>'ASC');
        $arFilter = array('IBLOCK_ID' => $this->iblock_id);
        $arSelect = array('UF_ID', 'UF_PARENT_ID');
        $db_list = $bs->GetList($arSort, $arFilter, false, $arSelect);
        while($category = $db_list->Fetch()) {
            $this->Add2Session(
                $category['ID'],
                $category['NAME'],
                $category['CODE'],
                $category['UF_ID'],
                $category['UF_PARENT_ID']
            );
        }

        if(!empty($_SESSION['cats'])) {
            $this->eco('Существующие категории: '.count($_SESSION['cats']).' шт.<br>');
        } else {
            $this->eco('На сайте ещё нет категорий<br>');
        }
    }

    /**
     * Получаем категории из XML и добавляем их
     * @param $xml
     */
    function GetFromXML($xml) {
        $update = 0;
        foreach($xml as $xml_cat) {
            $NAME = (string)$xml_cat;
            $UF_ID = (int)$xml_cat->attributes()->id;
            $UF_PARENT_ID = (int)$xml_cat->attributes()->parentid;


            if(!in_array($UF_ID, $_SESSION['cats_uf_ids'])) {
                $ID = $this->AddCat($NAME, $UF_ID, $UF_PARENT_ID);
                $this->Add2Session(
                    $ID,
                    $NAME,
                    $this->translitIt($NAME),
                    $UF_ID,
                    $UF_PARENT_ID
                );
                $update++;
            }
        }

        $this->eco('Количество XML категорий: '.count($xml).' шт.<br>');
        $this->eco('Добавлено '.$update.' категорий.<br>');
    }

    /**
     * Создаем связи между категориями
     */
    function MakeRelation() {

        foreach($_SESSION['cats'] as $CAT) {
            if($CAT['UF_PARENT_ID'] > 0) {
                $SID = $this->GetParentID($CAT['UF_PARENT_ID']);
                $bs = new CIBlockSection;
                $arFields = Array('IBLOCK_SECTION_ID' => $SID);
                $st = $bs->Update($CAT['ID'], $arFields);
                if($st === false){
                    $this->eco($bs->LAST_ERROR); die;
                }
            }
        }
    }

    /**
     * Получаем ID категории сайта из родителя XML
     * @param $PARENT_ID
     * @return mixed
     */
    function GetParentID($PARENT_ID) {
        foreach($_SESSION['cats'] as $CAT) {
            if($PARENT_ID == $CAT['UF_ID']) {
                return $CAT['ID'];
                break;
            }
        }
    }

    /**
     * Добовляем категорию
     * @param $NAME
     * @param $UF_ID
     * @param $UF_PARENT_ID
     * @param bool $CODE
     * @return bool|int
     */
    function AddCat($NAME, $UF_ID, $UF_PARENT_ID, $CODE = false) {

        $bs = new CIBlockSection;
        $arFields = Array(
            'IBLOCK_ID' => $this->iblock_id,
            'IBLOCK_SECTION_ID' => false,
            'NAME' => $NAME,
            'CODE' => !empty($CODE)?$CODE:$this->translitIt($NAME),
            'UF_ID' => $UF_ID,
            'UF_PARENT_ID' => $UF_PARENT_ID,
        );
        $id = $bs->Add($arFields);

        if(!$id){
            if(strlen($bs->LAST_ERROR) == 91) {
                $id = $this->AddCat(
                    $NAME,
                    $UF_ID,
                    $UF_PARENT_ID,
                    $this->translitIt($NAME.'_'.$UF_PARENT_ID)
                );

                return $id;
            } else {
                $this->eco($bs->LAST_ERROR);die;
            }
        } else {
            $this->eco('Добавлена категория: '.$NAME.'<br>');
            return $id;
        }
    }

    /**
     * Сохраняем все в сессию для пошаговости
     * @param $id
     * @param $name
     * @param $code
     * @param $uf_id
     * @param $uf_parent_id
     */
    function Add2Session($id, $name, $code, $uf_id, $uf_parent_id) {
        $_SESSION['cats'][$id]['ID'] = $id;
        $_SESSION['cats'][$id]['NAME'] = $name;
        $_SESSION['cats'][$id]['CODE'] = $code;
        $_SESSION['cats'][$id]['UF_ID'] = $uf_id;
        $_SESSION['cats'][$id]['UF_PARENT_ID'] = $uf_parent_id;
        $_SESSION['cats_uf_ids'][] = $uf_id;
    }


}

class Offer extends Category {

    /**
     * Добавляем элемент
     * @param $offer
     * @return bool
     */
    function Add($offer) {

        $cat = $this->GetParentID((int)$offer->categoryId);
        if($cat === false) {
            return false;
        }

        $el = new CIBlockElement;

        // Свойства
        $PROP = array();
        $PROP['id'] = (int)$offer->attributes()->id;
        $PROP['available'] = (string)$offer->attributes()->available;
        $PROP['url'] = (string)$offer->url;
        $PROP['price'] = (string)$offer->price;

        // Массив картинок
        if(count($offer->picture) > 1) {
            foreach($offer->picture as $picture) {
                $PROP['picture'][] = CFile::MakeFileArray($picture);
            }
        } else {
            $PROP['picture'] = CFile::MakeFileArray($offer->picture);
        }

        $PROP['prop_shoose_size'] = (string)$offer->prop_shoose_size;
        $PROP['prop_close_size'] = (string)$offer->prop_close_size;

        // Основное
        $arLoadProductArray = Array(
            "IBLOCK_SECTION_ID" => $cat,
            "IBLOCK_ID"      => $this->iblock_id,
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => (string)$offer->name,
            "CODE"           => $this->translitIt((string)$offer->name),
            "ACTIVE"         => "Y",
            "PREVIEW_TEXT"   => Array(
                "VALUE" => Array (
                    "TEXT" => substr((string)$offer->description, 0, 255),
                    "TYPE" => "text")
            ),
            "DETAIL_TEXT"    => Array(
                "VALUE" => Array (
                    "TEXT" => (string)$offer->description,
                    "TYPE" => "html")
            ),
        );

        // Добавляем
        if($id = $el->Add($arLoadProductArray)) {
            return $id;
        } else {
            die($el->LAST_ERROR);
        }
    }

    /**
     * Обновляем элемент
     * @param $id
     * @param $offer
     * @return bool1
     */
    function Update($id, $offer) {

        $el = new CIBlockElement;

        // Свойства
        $PROP = array();
        $PROP['id'] = (int)$offer->attributes()->id;
        $PROP['available'] = (string)$offer->attributes()->available;
        $PROP['url'] = (string)$offer->url;
        $PROP['price'] = (string)$offer->price;
        $PROP['picture'] = (string)$offer->picture;
        $PROP['prop_shoose_size'] = (string)$offer->prop_shoose_size;
        $PROP['prop_close_size'] = (string)$offer->prop_close_size;

        // Основное
        $arLoadProductArray = Array(
            "IBLOCK_SECTION_ID" => $this->GetParentID((int)$offer->categoryId),
            "PROPERTY_VALUES"=> $PROP,
            "NAME"           => (string)$offer->name,
            "ACTIVE"         => "Y",
            "PREVIEW_TEXT"   => substr((string)$offer->description, 0, 255),
            "DETAIL_TEXT"    => (string)$offer->description
        );

        // Обновляем
        $result = $el->Update($id, $arLoadProductArray);
        if(!empty($result)) {
            return true;
        } else {
            die($el->LAST_ERROR);
        }
    }

    /**
     * Получаем список всех элементов
     */
    function GetAll() {

        $bs = new CIBlockElement();

        $res = $bs->GetList(
            array('SORT'=>'ASC'),
            array('IBLOCK_ID' => $this->iblock_id),
            false,
            false,
            array('ID','PROPERTY_id')
        );

        while($ob = $res->GetNextElement())
        {
            $ar_fields = $ob->GetFields();
            $_SESSION['offers'][$ar_fields['ID']] = $ar_fields['PROPERTY_ID_VALUE'];
        }

        $this->eco(count($_SESSION['offers']) . ' - Кол-во элементов<br>');
    }

}

class ImportConfig {
    public $iblock_id = 6;
    public $debug = true;

    function eco($data) {
        if($this->debug === true) {
            echo $data;
        }
    }

    function translitIt($str) {
        $str = Translit::transliterate($str);
        $str = Translit::asURLSegment($str);
        return $str;
    }
}

final class Translit{
    /**
     * Укр/Рус символы
     *
     * @var array
     * @access private
     * @static
     */
    static private $cyr = array(
        'Щ',  'Ш', 'Ч', 'Ц','Ю', 'Я', 'Ж', 'А','Б','В','Г','Д','Е','Ё','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х', 'Ь','Ы','Ъ','Э','Є','Ї','І',
        'щ',  'ш', 'ч', 'ц','ю', 'я', 'ж', 'а','б','в','г','д','е','ё','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х', 'ь','ы','ъ','э','є','ї', 'і');

    /**
     * Латинские соответствия
     *
     * @var array
     * @access private
     * @static
     */
    static private $lat = array(
        'Shh','Sh','Ch','C','Ju','Ja','Zh','A','B','V','G','D','Je','Jo','Z','I','J','K','L','M','N','O','P','R','S','T','U','F','Kh','Y','Y','','E','Je','Ji','I',
        'shh','sh','ch','c','ju','ja','zh','a','b','v','g','d','je','jo','z','i','j','k','l','m','n','o','p','r','s','t','u','f','kh','y','y','','e','je','ji', 'i');

    /**
     * Приватный конструктор класса
     * не дает создавать объект этого класса
     *
     * @access private
     */
    private function __construct() {}

    /**
     * Статический метод транслитерации
     *
     * @param string
     * @return string
     * @access public
     * @static
     */

    static public function transliterate($string, $wordSeparator = '', $clean = false) {
        //$str = iconv($encIn, "utf-8", $str);

        for($i=0; $i<count(self::$cyr); $i++){
            $string = str_replace(self::$cyr[$i], self::$lat[$i], $string);
        }

        $string = preg_replace("/([qwrtpsdfghklzxcvbnmQWRTPSDFGHKLZXCVBNM]+)[jJ]e/", "\${1}e", $string);
        $string = preg_replace("/([qwrtpsdfghklzxcvbnmQWRTPSDFGHKLZXCVBNM]+)[jJ]/", "\${1}y", $string);
        $string = preg_replace("/([eyuioaEYUIOA]+)[Kk]h/", "\${1}h", $string);
        $string = preg_replace("/^kh/", "h", $string);
        $string = preg_replace("/^Kh/", "H", $string);

        $string = trim($string);

        if ($wordSeparator) {
            $string = str_replace(' ', $wordSeparator, $string);
            $string = preg_replace('/['.$wordSeparator.']{2,}/','', $string);
        }

        if ($clean) {
            $string = strtolower($string);
            $string = preg_replace('/[^-_a-z0-9]+/','', $string);
        }

        //return iconv("utf-8", $encOut, $str);

        return $string;
    }

    /**
     * Приведение к УРЛ
     *
     * @return string
     * @access public
     * @static
     */
    static public function asURLSegment($string){
        return strtolower(self::transliterate($string, '_', true));
    }

}