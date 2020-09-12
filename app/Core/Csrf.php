<?php

namespace ForkBB\Core;

use ForkBB\Core\Secury;

class Csrf
{
    /**
     * @var Secury
     */
    protected $secury;

    /**
     * @var string
     */
    protected $key;

    public function __construct(Secury $secury, string $key)
    {
        $this->secury = $secury;
        $this->key = \sha1($key);
    }

    /**
     * Возвращает csrf токен
     */
    public function create(string $marker, array $args = [], /* string|int */ $time = null): string
    {
         unset($args['token'], $args['#']);
         \ksort($args);
         $marker .= '|';
         foreach ($args as $key => $value) {
             $marker .= $key . '|' . (string) $value . '|';
         }
         $time = $time ?: \time();

         return $this->secury->hmac($marker, $time . $this->key) . 'f' . $time;
    }

    /**
     * Проверка токена
     */
    public function verify($token, string $marker, array $args = []): bool
    {
        return \is_string($token)
            && \preg_match('%f(\d+)$%D', $token, $matches)
            && $matches[1] < \time()
            && $matches[1] + 1800 > \time()
            && \hash_equals($this->create($marker, $args, $matches[1]), $token);
    }
}
