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
            throw new \RuntimeException('Empty notificationType', 400);
        }

        $reseller = Seller::getById($dataAdapter->resellerId());
        if (!$reseller) {
            throw new \RuntimeException('Seller not found!', 400);
        }

        $client = Contractor::getById($dataAdapter->clientId());
        if (!$client || $client->type !== Contractor::TYPE_CUSTOMER || $client->Seller->id !== $dataAdapter->resellerId()) {
            throw new \RuntimeException('сlient not found!', 400);
        }

        $clientFullName = $client->getFullName() ?: $client->name;

        $creator = Employee::getById($dataAdapter->creatorId());
        if (!$creator) {
            throw new \RuntimeException('Creator not found!', 400);
        }

        $expert = Employee::getById($dataAdapter->expertId());
        if (!$expert) {
            throw new \RuntimeException('Expert not found!', 400);
        }

        $differences = '';
        if ($dataAdapter->notificationType() === self::TYPE_NEW) {
            $differences = __('NewPositionAdded', null, $dataAdapter->resellerId());
        } elseif ($dataAdapter->notificationType() === self::TYPE_CHANGE && $dataAdapter->differences()) {
            $differences = __('PositionStatusHasChanged', [
                'FROM' => Status::getName($dataAdapter->differencesFrom()),
                'TO' => Status::getName($dataAdapter->differencesTo()),
            ], $dataAdapter->resellerId());
        }

        $templateData = [
            'COMPLAINT_ID' => $dataAdapter->complaintId(),
            'COMPLAINT_NUMBER' => $dataAdapter->complaintNumber(),
            'CREATOR_ID' => $dataAdapter->creatorId(),
            'CREATOR_NAME' => $creator->getFullName(),
            'EXPERT_ID' => $dataAdapter->expertId(),
            'EXPERT_NAME' => $expert->getFullName(),
            'CLIENT_ID' => $dataAdapter->clientId(),
            'CLIENT_NAME' => $clientFullName,
            'CONSUMPTION_ID' => $dataAdapter->consumptionId(),
            'CONSUMPTION_NUMBER' => $dataAdapter->consumptionNumber(),
            'AGREEMENT_NUMBER' => $dataAdapter->agreementNumber(),
            'DATE' => $dataAdapter->date(),
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

        $emailFrom = getResellerEmailFrom($dataAdapter->resellerId());
        // Получаем email сотрудников из настроек
        $emails = getEmailsByPermit($dataAdapter->resellerId(), 'tsGoodsReturn');
        if ($emailFrom && count($emails) > 0) {
            $messages = [];
            foreach ($emails as $email) {
                $messages = [ // MessageTypes::EMAIL
                    'emailFrom' => $emailFrom,
                    'emailTo' => $email,
                    'subject' => __('complaintEmployeeEmailSubject', $templateData, $dataAdapter->resellerId()),
                    'message' => __('complaintEmployeeEmailBody', $templateData, $dataAdapter->resellerId()),
                ];
            }
            MessagesClient::sendMessage($messages, $dataAdapter->resellerId(), NotificationEvents::CHANGE_RETURN_STATUS);
            $result['notificationEmployeeByEmail'] = true;
        }

        // Шлём клиентское уведомление, только если произошла смена статуса
        if ($dataAdapter->notificationType() === self::TYPE_CHANGE && $dataAdapter->differencesTo()) {
            if ($emailFrom && $client->email) {
                try {
                    MessagesClient::sendMessage([
                        [ // MessageTypes::EMAIL
                            'emailFrom' => $emailFrom,
                            'emailTo' => $client->email,
                            'subject' => __('complaintClientEmailSubject', $templateData, $dataAdapter->resellerId()),
                            'message' => __('complaintClientEmailBody', $templateData, $dataAdapter->resellerId()),
                        ],
                    ], $dataAdapter->resellerId(), $client->id, NotificationEvents::CHANGE_RETURN_STATUS, $dataAdapter->differencesTo());

                    $result['notificationClientByEmail']['isSent'] = true;

                } catch (\Exception $ex) {
                    //TODO::Логируем ошибку/отправляем условную сентри
                    $result['notificationClientByEmail']['message'] = $ex->getMessage();
                }
            }

            if ($client->mobile) {
                try {
                    if (NotificationManager::send($dataAdapter->resellerId(), $client->id, NotificationEvents::CHANGE_RETURN_STATUS, $dataAdapter->differencesTo(), $templateData)) {
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
