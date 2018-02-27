<?php

/**
 * Получаем из LDAP пользователей и группы и сохраняем их в oracle
 * Типы: 0 - пользователи, 1 - группы.
 * Вызов комманды ./yiic ldapsync из noc-new/protected
 * Используется постраничное получение результатов,
 * т.к. компонент ldap ни при каких условиях не хочет возвращать более 1000 результатов одним ответом.
 * Class LdapSyncCommand
 */
class LdapSyncCommand extends CConsoleCommand
{
    const LDAP_SERVER = 'ldap://123.ru/'; //Сервер подключения

    const LDAP_BASE = 'dc=ad,dc=123,dc=ru'; //Директория поиска

    const LDAP_USER = '123'; //Пользователь

    const LDAP_PWD = '123'; //Пароль

    const LDAP_USER_TYPE = 0; //Поле type в oracle

    const LDAP_GROUP_TYPE = 1; //Поле type в oracle

    /**
     * Фильтры для поиска пользователей и групп
     * Ключи: 0 - пользователи, 1 - группы.
     * @var array
     */
    private $filter = [
        self::LDAP_USER_TYPE => '(&(sAMAccountType=805306368)(mail=*))',
        self::LDAP_GROUP_TYPE => '(&(objectclass=group)(mail=*))',
    ];

    /** @var array Массив нужных нам атрибутов */
    private $attributes = ['mail', 'displayname'];

    /** @var resource Подключение к серверу LDAP */
    private $connect;

    /**
     * @inheritdoc Создаем подключение.
     */
    public function init()
    {
        $this->connect = ldap_connect(self::LDAP_SERVER);
        parent::init();
    }

    /**
     * @throws CException
     */
    public function actionIndex()
    {
        //обязательные два set_option, иначе не ищет в корневой дирректории
        //и обязательно вызываются до ldap_bind
        ldap_set_option($this->connect, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($this->connect, LDAP_OPT_PROTOCOL_VERSION, 3);

        ldap_bind($this->connect, self::LDAP_USER, self::LDAP_PWD);

        $transaction = Yii::app()->getDb()->beginTransaction();

        try {
            Yii::app()->db->createCommand('DELETE A_DBA.LDAP_CONTACT')->execute();

            $this->search(self::LDAP_USER_TYPE);
            $this->search(self::LDAP_GROUP_TYPE);

            $transaction->commit();
        } catch (Exception $e) {
            Yii::log('Ошибка при записи в базу: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'LDAP');
            $transaction->rollback();
        }
    }

    /**
     * @param int $type
     * @return bool
     * @throws Exception
     */
    private function search($type)
    {
        $cookie = ''; //нужно для постраничного поиска

        do {
            ldap_control_paged_result($this->connect, 1000, true, $cookie);

            $result = ldap_search($this->connect, self::LDAP_BASE, $this->filter[$type], $this->attributes);

            $entries = ldap_get_entries($this->connect, $result);

            if (!empty($entries)) {
                foreach ($entries as $item) {
                    if (!empty($item['displayname'][0])) {
                        $model = new \LdapContact();
                        $model->TYPE = $type;
                        $model->DISPLAYNAME = $item['displayname'][0];
                        $model->EMAIL = $item['mail'][0];
                        if (!$model->save()){
                            Yii::app()->sentry->getRaven()->extra_context(['ошибка' => $model->getErrors()]);
                            throw new Exception(400, 'Не удалось сохранить email: ' . $model->EMAIL);
                        }
                    }
                }
            }
            ldap_control_paged_result_response($this->connect, $result, $cookie);
        } while (!empty($cookie));

        return true;
    }
}
