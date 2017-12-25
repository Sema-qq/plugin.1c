<?php

//без обращения в модель
    private function updateOrderState()
    {
        $dir = $_SERVER['DOCUMENT_ROOT'].'/wa-data/protected/shop/plugins/cml1c/';
        $files = scandir($dir);
        foreach ($files as $value) {
            $extensions = substr(strrchr($value, '.'), 1);
            if ($extensions == 'zip') {
                $zip_name = $value;
            }
        }
        $zip = new ZipArchive;
        if ($dir.$zip_name) {
            $zip->open($dir.$zip_name);
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $rest = substr($zip->getNameIndex($i), 0, 6);
                if ($rest == 'orders') {
                    $filename = $zip->getNameIndex($i);
                }
            }
            $zip->extractTo($dir);
            $zip->close();
        }
        $xml = simplexml_load_file($dir.$filename);
        foreach ($xml->Документ as $document) {
            $order = substr($document->Номер, 4);
            foreach ($document->ЗначенияРеквизитов->ЗначениеРеквизита as $requisites) {
                //$model = new shopOrderModel();
                if ($requisites->Значение == 'Заказ в работе') {
                    $this->updateStatusOrder($order, 'processing');
                }
                elseif ($requisites->Значение == 'Поступила оплата по заказу') {
                    $this->updateStatusOrder($order, 'paid');
                }
                elseif ($requisites->Значение == 'Заказ отгружен') {
                    $this->updateStatusOrder($order, 'shipped');
                }
                elseif ($requisites->Значение == 'Заказ выполнен') {
                    $this->updateStatusOrder($order, 'completed');
                }
            }
        }
        //fopen($_SERVER['DOCUMENT_ROOT'].'/wa-data/protected/shop/plugins/cml1c/cookie.txt', 'w');
        waFiles::delete($dir.$filename);
        waFiles::delete($dir.$zip_name);
    }

    private function updateStatusOrder($id, $status)
    {
        $model = new shopOrderModel();
        $message = date("Y-m-d H:i:s")."\n";
        $dir_log = $_SERVER['DOCUMENT_ROOT'].'/wa-data/protected/shop/plugins/cml1c/log.txt';
        file_put_contents($dir_log, 'Мы в модель попали'.$message);
        $data = ['state_id' => $status];
        if($model->updateById($id, $data)){
            file_put_contents($dir_log, 'Мы добавили в базу'.$message, FILE_APPEND | LOCK_EX);
        }
    }
