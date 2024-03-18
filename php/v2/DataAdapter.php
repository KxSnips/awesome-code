<?php

namespace NW\WebService\References\Operations\Notification;

class DataAdapter
{
    private $data;

    public static function build($data): DataAdapter
    {
        return new static($data);
    }

    public function __construct($data)
    {
        if (!$data) {
            throw new \Exception('Empty data!', 400);
        }

        $this->data = $data;
    }

    /**
     * @return int|null
     */
    public function resellerId(): ?int
    {
        return (int)$this->getFromData($this->data, 'resellerId');
    }

    public function notificationType(): ?int
    {
        return (int)$this->getFromData($this->data, 'notificationType');
    }

    public function clientId(): ?int
    {
        return (int)$this->getFromData($this->data, 'clientId');
    }

    public function creatorId(): ?int
    {
        return (int)$this->getFromData($this->data, 'creatorId');
    }

    public function expertId(): ?int
    {
        return (int)$this->getFromData($this->data, 'expertId');
    }

    public function differences(): ?array
    {
        return $this->getFromData($this->data, 'differences');
    }

    public function differencesFrom(): ?int
    {
        return (int)$this->getFromData($this->data, 'differences.from');
    }

    public function differencesTo(): ?int
    {
        return (int)$this->getFromData($this->data, 'differences.to');
    }

    public function complaintId(): ?int
    {
        return (int)$this->getFromData($this->data, 'complaintId');
    }

    public function complaintNumber(): ?string
    {
        return (string)$this->getFromData($this->data, 'complaintNumber');
    }

    public function consumptionId(): ?int
    {
        return (int)$this->getFromData($this->data, 'consumptionId');
    }

    public function consumptionNumber(): ?string
    {
        return (string)$this->getFromData($this->data, 'consumptionNumber');
    }

    public function agreementNumber(): ?string
    {
        return (string)$this->getFromData($this->data, 'agreementNumber');
    }

    public function date(): ?string
    {
        return (string)$this->getFromData($this->data, 'date');
    }

    /**
     * Безопасное получение значения из массива $data
     * Конечно функция никак не привязана именно к этому классу,
     * по-хорошему её вынести в отдельный класс для общего использования.
     * @param $data
     * @param $key
     * @return mixed|null
     */
    private function getFromData($data, $key): mixed
    {
        if (is_null($key)) {
            return $data;
        }

        if (isset($data[$key])) {
            return $data[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }
}