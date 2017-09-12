<?php
/*
Доработка плагина 1C-CommerceML Shop-Script 7
1. wa-apps->shop->plugins->cml1c->lib->actions->frontend->shopCml1cPluginFrontendController->importSale()->сменить true на false в условном операторе
2. wa-data->protected->shop->->plugins->cml1c->начнут появляться архивы каждый автоматический обмен, либо когда изменение происходит с заказом со стороны 1с, нужно посмотреть есть ли там статусы заказов, если нет, то попросить 1с-ника добавить их туда
3. Статусов заказов нет, их может добавить 1С программист или можно обойтись без них. Т.к. в значениях реквизитов есть строки об оплате и отправке (Дата оплаты по 1С и Дата отгрузки по 1С), можно по ним ставить статусы "отправлен/оплачен", а в ином случае "в процессе".
4. В метод importSale()->после вызова метода response() дописать строку $this->updateOrderState(); 
5. Добавить в этом же файле свой приватный метод:
*/
    private function updateOrderState()
    {
        //путь к папке с архивом
        $dir = $_SERVER['DOCUMENT_ROOT'].'/wa-data/protected/shop/plugins/cml1c/';
        //сканируем папку
        $files = scandir($dir);
        //перебираем полученный массив
        foreach ($files as $value) {
            //обрезаем все после расширения, т.к. имя файла не знаем
            $extensions = substr(strrchr($value, '.'), 1);
            //ищем тот, что zip
            if ($extensions == 'zip') {
                //получаем имя файла
                $zip_name = $value;
            }
        }
        $zip = new ZipArchive;
        //если существует такой архив, то
        if ($dir.$zip_name) {
            //открываем его
            $zip->open($dir.$zip_name);
            //ищем нужный внутри файл, должен начинаться с orders
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $rest = substr($zip->getNameIndex($i), 0, 6);
                if ($rest == 'orders') {
                    //кладем имя файла в переменную
                    $filename = $zip->getNameIndex($i);
                }
            }
            //распаковываем архив
            $zip->extractTo($dir);
            $zip->close();
        }
        //получаем объект xml
        $xml = simplexml_load_file($dir.$filename);
        //перебираем все документы
        foreach ($xml->Документ as $document) {
            //ищем номера заказов
            $order = substr($document->Номер, 4);
            //перебираем значения в каждом заказе
            foreach ($document->ЗначенияРеквизитов->ЗначениеРеквизита as $requisites) {
                $model = new shopOrderModel();
                //если статусы 1С выводятся в файл то,
                //проверяем статус и если такой то статус, то передаем такое то значение в модель
                if ($requisites->Значение == 'Заказ в работе') {
                    //обращаемся к модели
                    $model->updateStatusOrder($order, 'processing');
                }
                elseif ($requisites->Значение == 'Поступила оплата по заказу') {
                    $model->updateStatusOrder($order, 'paid');
                }
                elseif ($requisites->Значение == 'Заказ отгружен') {
                    $model->updateStatusOrder($order, 'shipped');
                }
                elseif ($requisites->Значение == 'Заказ выполнен') {
                    $model->updateStatusOrder($order, 'completed');
                }
                //если статусы 1С не выводятся в файл
                //если оплачен  в 1С, то ставим оплачен на сайте
                /*           
                if ($requisites->Наименование == 'Дата оплаты по 1С') {
                    updateStatusOrder($order, 'paid');
                }
                //если уже отправлен, то ставим отправлен
                elseif ($requisites->Наименование == 'Дата отгрузки по 1С') {
                    updateStatusOrder($order, 'shipped');
                }
                //иначе просто подтвержден
                else {
                    updateStatusOrder($order, 'processing';
                }
                */
            }
        }
        //fopen($_SERVER['DOCUMENT_ROOT'].'/wa-data/protected/shop/plugins/cml1c/cookie.txt', 'w');
        //в итоге удаляем файл и архив, если не средствами вебасиста, то unlink
        waFiles::delete($dir.$filename);
        waFiles::delete($dir.$zip_name);
    }
//6. В /wa-apps/shop/lib/model/shopOrderModel добавить свой метод
    public function updateStatusOrder($id, $status)
    {
        //кладем дату в логи
        $message = date("Y-m-d H:i:s")."\n";
        //путь для создания лога
        $dir_log = $_SERVER['DOCUMENT_ROOT'].'/wa-data/protected/shop/plugins/cml1c/log.txt';
        //если в модель попали, запишем при этом удалив старый лог
        file_put_contents($dir_log, 'Мы в модель попали'.$message);
        //массив для передачи в метод, ключ это поле, значение это то, что нужно положить в базу
        $data = ['state_id' => $status];
        //если апдейт прошел, то
        if($this->updateById($id, $data)){
            //допишем в существующий лог, что в базу добавили
            file_put_contents($dir_log, 'Мы добавили в базу'.$message, FILE_APPEND | LOCK_EX);
        }
    }
//7. Проверяем, что все работает.


//если на локалке проверяем, то можно использовать такой метод
function updateStatusOrder ($id, $status)
{
    //если подключение к базе есть, то
    if ($mysqli = new mysqli("localhost", "root", "1", "test")) {
        $mysqli->query ("SET NAMES 'utf-8'");
        //обновляем статус заказа пришедший по айди
        $mysqli->query("UPDATE `shop_order` SET `state_id` ='".$status."' WHERE `id`= ".$id);

    }else{ echo "fail";}
}

//так же есть еще вариант достать не распаковывая архив, но тут минус, т.к. файлы не перебрать, и если их там много, то возьметься первый xml
$zip = zip_open($zip_name);
$zip_entry = zip_read($zip);
$zip_entry_read = zip_entry_read($zip_entry, 10000000);
$xml = simplexml_load_string($zip_entry_read);
$xml = new SimpleXMLElement($zip_entry_read);
