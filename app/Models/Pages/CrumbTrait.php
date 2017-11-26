<?php

namespace ForkBB\Models\Pages;

use ForkBB\Models\Model;

trait CrumbTrait 
{
    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     * 
     * @param mixed $args
     * 
     * @return array
     */
    protected function crumbs(...$args)
    {
        $crumbs = [];
        $active = true;

        foreach ($args as $arg) {
            // Раздел или топик
            if ($arg instanceof Model) {
                while (null !== $arg->parent && $arg->link) {
                    if (isset($arg->forum_name)) {
                        $name = $arg->forum_name;
                    } elseif (isset($arg->subject)) {
                        $name = $arg->cens()->subject;
                    } else {
                        $name = 'no name';
                    }

                    $this->titles = $name;
                    $crumbs[] = [$arg->link, $name, $active];
                    $active = null;
                    $arg = $arg->parent;
                }
            // Строка
            } else {
                $this->titles = (string) $arg;
                $crumbs[] = [null, (string) $arg, $active];
            }
            $active = null;
        }
        // главная страница
        $crumbs[] = [$this->c->Router->link('Index'), __('Index'), $active];

        return array_reverse($crumbs);
    }
}
