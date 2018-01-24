<?php

use application\models\Fias\FiasAddrObjNew;
use application\models\Fias\FiasSocrBaseNew;

/**
 * Class FiasCommand
 * Скачиваем базу Фиас (архив 5-6гб)
 * распарсиваем её (конкретно файл AS_ADDROBJ...) и заносим  в бд.
 * (в будущем планируется распарсить и файл AS_SOCRBASE..)
 * запуск комманды: ./yiic fias --addrobj
 * параметрами передаются файлы, которые нужно распарсить
 * когда понадобиться парсить второй файл, запускать ./yiic fias --addrobj --socrbase
 * Если хоть где то ошибка, нужно всё остановить! (т.к. нужны полные актуальные данные)
 * 'http://fias.nalog.ru/Public/Downloads/Actual/fias_xml.rar' ссылка на актуальную базу
 */
class FiasCommand extends CConsoleCommand
{
    /**
     * Имя, которое мы присвоим скачанному архиву
     */
    const RARNAME = 'fias_xml.rar';


    /**
     * Перед запуском файла очистим таблицу и удалим предыдущий архив
     * Запускаем скачивание файла, затем парсеры
     * Если всё успешно, то удалим файлы, которые распарсили
     * @param bool $addrobj
     * @param bool $socrbase
     */
    public function actionIndex($addrobj = false, $socrbase = false)
    {
        //предварительно очищаем таблицу (на truncate нету прав у user_noc)
        Yii::app()->db->createCommand('DELETE A_DBA.FIAS_ADDROBJ_NEW')->execute();
        //прошлый архив удаляем перед скачиванием нового архива.
        if (file_exists(Yii::app()->params['uploadDir'] . self::RARNAME)) {
            unlink(Yii::app()->params['uploadDir'] . self::RARNAME);
        }
        if ($this->download()) {
            if ($files = $this->getFilename($addrobj, $socrbase)){
                if (!empty($files['AS_ADDROBJ']) && file_exists($files['AS_ADDROBJ'])) {
                    if ($this->parse($files['AS_ADDROBJ'], 'Object', '\application\models\Fias\FiasAddrObjNew')) {
                        unlink($files['AS_ADDROBJ']);
                    }
                }
                if (!empty($files['AS_SOCRBASE']) && file_exists($files['AS_SOCRBASE'])) {
                    if ($this->parse($files['AS_SOCRBASE'], 'AddressObjectType', '\application\models\Fias\FiasSocrBaseNew')) {
                        unlink($files['AS_SOCRBASE']);
                    }
                }
            }
        }
    }

    /**
     * Скачиваем архив
     * @return bool
     */
    private function download()
    {
        try {
            $ch = curl_init(Yii::app()->params['fiasDownloadUrl']);
            $fp = fopen(Yii::app()->params['uploadDir'] . self::RARNAME, 'w');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            return true;
        } catch (Exception $e) {
            Yii::log('Ошибка при загрузке файла: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'Fias');
            return false;
        }
    }

    /**
     * Разбираем архив, берем только нужные файлы.
     * @param bool $addrobj
     * @param bool $socrbase
     * @return array|bool
     */
    private function getFilename($addrobj, $socrbase)
    {
        try {
            $rar = RarArchive::open(Yii::app()->params['uploadDir'] . self::RARNAME);
            $files = [
                'AS_ADDROBJ' => '',
                'AS_SOCRBASE' => ''
            ];
            foreach ($rar->getEntries() as $entry) {
                if ((strripos($entry->getName(), 'AS_ADDROBJ') !== false) && (!empty($addrobj))) {
                    $files['AS_ADDROBJ'] = Yii::app()->params['uploadDir'] . $entry->getName();
                    $entry->extract(Yii::app()->params['uploadDir']);
                } elseif ((strripos($entry->getName(), 'AS_SOCRBASE') !== false) && (!empty($socrbase))) {
                    $files['AS_SOCRBASE'] = Yii::app()->params['uploadDir'] . $entry->getName();
                    $entry->extract(Yii::app()->params['uploadDir']);
                }
            }
            return $files;
        } catch (Exception $e) {
            Yii::log('Ошибка при распаковке архива: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'Fias');
            return false;
        }
    }

    /**
     * Распарсиваем файл по строчно, т.к. вес от 2.5гб
     * @param string $file
     * @param string $readerName
     * @param string $modelName
     * @return bool
     */
    private function parse($file, $readerName, $modelName)
    {
        $reader = new XMLReader();
        $reader->open($file);
        try {
            while ($reader->read()) {
                $data = [];
                if ($reader->name == $readerName) {
                    $arr = explode(' ', $reader->readOuterXml());
                    foreach ($arr as $key => $value) {
                        if ($key == 0) {
                            continue;
                        }
                        $val = $this->getAttr($value);
                        if ($val !== '') {
                            $data[$val] = $reader->getAttribute($val);
                            //форматируем даты
                            if (strripos($val, 'DATE') !== false) {
                                $data[$val] = date('d.m.Y', strtotime($data[$val]));
                            }
                            //LEVEL зарезервированное слово в oracle, поэтому заменяем на LVL
                            if ($val == 'LEVEL') {
                                $data['LVL'] = $data[$val];
                                unset($data[$val]);
                            }
                        }
                    }
                    $this->insert($data, $modelName);
                }
            }
        } catch (Exception $e) {
            Yii::log('Ошибка при парсинге файла: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'Fias');
            return false;
        }
        return true;
    }

    /**
     * Сохраняем в базу
     * @param array $data
     * @param string $modelName
     * @return bool
     */
    public function insert($data, $modelName)
    {
        $model = new $modelName();
        $model->unsetAttributes();
        return $model->load($data, '') && $model->save();
    }

    /**
     * Из строки получаем только нужное нам название атрибута
     * @param string $value
     * @return mixed
     */
    private function getAttr($value)
    {
        return str_replace('=', '', substr($value, 0, strpos($value, '=')));
    }
}
