<?php

namespace NW\WebService\References\Operations\Notification;

class TsReturnOperation extends ReferencesOperation
{
    public const TYPE_NEW = 1;
    public const TYPE_CHANGE = 2;

    /**
     * @throws \Exception
     */
    public function doOperation(): array
    {
        $result = [
            'notificationEmployeeByEmail' => false,
            'notificationClientByEmail' => [
                'isSent' => false,
                'message' => '',
            ],
            'notificationClientBySms' => [
                'isSent' => false,
                'message' => '',
            ],
        ];

        $dataAdapter = DataAdapter::build((array)$this->getRequest('data'));

        if (!$dataAdapter->resellerId()) {
            $result['notificationClientBySms']['message'] = 'Empty resellerId';
            return $result;
        }


        if (!$dataAdapter->notificationType()) {
            throw new \Exception('Empty notificationType', 400);
        }

        $reseller = Seller::getById($dataAdapter->resellerId());
        if (!$reseller) {
            throw new \Exception('Seller not found!', 400);
        }

        $client = Contractor::getById($dataAdapter->clientId());
        if (!$client || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $dataAdapter->resellerId()) {
            throw new \Exception('сlient not found!', 400);
        }

        $clientFullName = $client->getFullName() ? :$client->name;
        if (!$client->getFullName()) {
            $clientFullName = $client->name;
        }

        $creatorId = (int)$this->getFromData($data, 'creatorId');
        $creator = Employee::getById($creatorId);
        if (!$creator) {
            throw new \Exception('Creator not found!', 400);
        }

        $expertId = (int)$this->getFromData($data, 'expertId');
        $expert = Employee::getById($expertId);
        if (!$expert) {
            throw new \Exception('Expert not found!', 400);
        }

        $differences = '';
        $differencesFrom = (int)$this->getFromData($data, 'differences.from');
        $differencesTo = (int)$this->getFromData($data, 'differences.to');
        if ($notificationType === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $resellerId);
        } elseif ($notificationType === self::TYPE_CHANGE && $this->getFromData($data, 'differences')) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName($differencesFrom),
                'TO' => Status::getName($differencesTo),
            ], $resellerId);
        }

        $templateData = [
            'COMPLAINT_ID' => (int)$this->getFromData($data, 'complaintId'),
            'COMPLAINT_NUMBER' => (string)$this->getFromData($data, 'complaintNumber'),
            'CREATOR_ID' => $creatorId,
            'CREATOR_NAME' => $creator->getFullName(),
            'EXPERT_ID' => $expertId,
            'EXPERT_NAME' => $expert->getFullName(),
            'CLIENT_ID' => $clientId,
            'CLIENT_NAME' => $clientFullName,
            'CONSUMPTION_ID' => (int)$this->getFromData($data, 'consumptionId'),
            'CONSUMPTION_NUMBER' => (string)$this->getFromData($data, 'consumptionNumber'),
            'AGREEMENT_NUMBER' => (string)$this->getFromData($data, 'agreementNumber'),
            'DATE' => (string)$this->getFromData($data, 'date'),
            'DIFFERENCES' => $differences,
        ];

        $emptyTemplate = [];

        // Если хоть одна переменная для шаблона не задана, то не отправляем уведомления
        foreach ($templateData as $key => $tempData) {
            if (empty($tempData)) {
                $emptyTemplate[] = $key;
            }
        }

        //В исключении показываем все пустые ключи для понимания.
        if ($emptyTemplate) {
            throw new \Exception("Template Data (" . implode(',', $emptyTemplate) . ") is empty!", 500);
        }

        $emailFrom = getResellerEmailFrom($resellerId);
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($resellerId, 'tsGoodsReturn');
        if ($emailFrom && count($emails) > 0) {
            $messages = [];
            foreach ($emails as $email) {
                $messages = [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo' => $email,
                    'subject' => __('complaintEmployeeEmailSubject', $templateData, $resellerId),
                    'message' => __('complaintEmployeeEmailBody', $templateData, $resellerId),
                ];
            }
            MessagesClient::sendMessage($messages, $resellerId, NotificationEvents::CHANGE_RETURN_STATUS);
            $result['notificationEmployeeByEmail'] = true;
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($notificationType === self::TYPE_CHANGE && $differencesTo) {
            if ($emailFrom && $client->email) {
                try {
                    MessagesClient::sendMessage([
                        [ // MessageTypes::EMAIL
                            'emailFrom' => $emailFrom,
                            'emailTo' => $client->email,
                            'subject' => __('complaintClientEmailSubject', $templateData, $resellerId),
                            'message' => __('complaintClientEmailBody', $templateData, $resellerId),
                        ],
                    ], $resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, $differencesTo);

                    $result['notificationClientByEmail']['isSent'] = true;

                } catch (\Exception $ex) {
                    //TODO::Логируем ошибку/отправляем условную сентри
                    $result['notificationClientByEmail']['message'] = $ex->getMessage();
                }
            }

            if ($client->mobile) {
                try {
                    if (NotificationManager::send($resellerId, $client->id, NotificationEvents::CHANGE_RETURN_STATUS, $differencesTo, $templateData)) {
                        $result['notificationClientBySms']['isSent'] = true;
                    }

                } catch (\Exception $ex) {
                    $result['notificationClientBySms']['message'] = $ex->getMessage();
                }
            }
        }

        return $result;
    }



}
