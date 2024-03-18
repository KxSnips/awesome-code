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