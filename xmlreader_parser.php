<?php  

    /**
     *Запускаем скачивание файла, затем парсеры, а в конце всё удаляем
     */
    public function actionIndex()
    {
        if ($this->download()) {
            $files = $this->getFilename();
            if (!empty($files)) {
                if ($this->parse($files['AS_ADDROBJ'], 'Object', 'FiasAddrObjNew')) {
                    unlink($files['AS_ADDROBJ']);
                }
//                раскомментировать условие ниже, когда понадобиться парсисть второй файл
//                if ($this->parse($files['AS_SOCRBASE'], 'AddressObjectType', 'FiasSocrBaseNew')) {
//                    unlink($files['AS_SOCRBASE']);
//                }
            }
            unlink(self::DIR . self::RARNAME);
        }
    }

    /**
     * Скачиваем архив
     * @return bool
     */
    private function download()
    {
        try {
            $ch = curl_init(self::URL);
            $fp = fopen(self::DIR . self::RARNAME, 'w');
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
     * Разбираем архив, берем только нужные файлы, остальные удаляем.
     * @return mixed
     */
    private function getFilename()
    {
        $rar = RarArchive::open(self::DIR . self::RARNAME);
        foreach ($rar->getEntries() as $entry) {
            if (strripos($entry->getName(), 'AS_ADDROBJ') !== false) {
                $files['AS_ADDROBJ'] = $entry->getName();
                $entry->extract(self::DIR);
            } elseif (strripos($entry->getName(), 'AS_SOCRBASE') !== false) {
                $files['AS_SOCRBASE'] = $entry->getName();
                $entry->extract(self::DIR);
            }
        }
        return !empty($files) ?: null;
    }

    /**
     * Распарсиваем файл по строчно, т.к. вес от 2.5гб
     * @param $file
     * @param $readerName
     * @param $modelName
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
                    try {
                        $this->insert($data, $modelName);
                    } catch (Exception $e) {
                        Yii::log('Ошибка при занесении в базу: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'Fias');
                        return false;
                    }
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
     * @param $data
     * @param $modelName
     * @return bool
     */
    public function insert($data, $modelName)
    {
        if ($modelName == 'FiasAddrObjNew') {
            $model = new FiasAddrObjNew();
        } elseif ($modelName == 'FiasSocrBaseNew') {
            $model =new FiasSocrBaseNew();
        }
        $model->unsetAttributes();
        return $model->load($data, '') && $model->save();
    }

    /**
     * Из строки получаем только нужное нам название атрибута
     * @param $value
     * @return mixed
     */
    private function getAttr($value)
    {
        return str_replace('=', '', substr($value, 0, strpos($value, '=')));
    }
?>
