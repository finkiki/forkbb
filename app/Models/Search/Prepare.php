<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Search;

use ForkBB\Models\Method;

class Prepare extends Method
{
    /**
     * Проверка и подготовка поискового запроса
     */
    public function prepare(string $query): bool
    {
        // обработка хэштега (может быть только один!)
        if (
            \preg_match('%^#(?=.{3})[\p{L}\p{N}]+(?:_+[\p{L}\p{N}]+)*$%uD', $query)
            && \is_string($tag = $this->model->word(\mb_strtolower($query, 'UTF-8')))
        ) {
            $this->model->queryError = null;
            $this->model->queryWords = [$tag];
            $this->model->queryText  = $tag;

            return true;
        // не парные кавычки
        } elseif (\substr_count($query, '"') % 2) {
            $this->model->queryError = 'Odd number of quotes: \'%s\'';
            $this->model->queryWords = [];
            $this->model->queryText  = $query;

            return false;
        }

        $error                   = null;
        $this->model->queryWords = null;
        $this->model->queryText  = null;

        $stack   = [];
        $quotes  = false;
        $words   = [];
        $keyword = true;
        $count   = 0;

        foreach (\preg_split('%"%', $query) as $subQuery) {
            // подстрока внутри кавычек
            if ($quotes) {
                $subQuery = \mb_strtolower(\trim($subQuery), 'UTF-8');
                // не стоп-слово и минимальная длина удовлетворяет условию
                if (null !== $this->model->word($subQuery)) {
                    // подстрока является словом и нет символов CJK языков
                    if (
                        false === \strpos($subQuery, ' ')
                        && ! $this->model->isCJKWord($subQuery)
                        && $this->model->cleanText($subQuery) === $subQuery
                    ) {
                        $words[] = $subQuery;
                    // это не слово или есть символы CJK языков
                    // искать придется через LIKE по тексту сообщений
                    } else {
                        $words[] = ['type' => 'CJK', 'word' => $subQuery];
                    }

                    $keyword = false;
                    ++$count;
                }

                $quotes  = false;

                continue;
            }

            // действуют управляющие слова
            foreach (
                \preg_split(
                    '%\s*(\b(?:AND|OR|NOT)\b|(?<![\p{L}\p{N}])\-|[()+|!])\s*|\s+%u',
                    $subQuery,
                    -1,
                    \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY
                ) as $cur
            ) {
                $key = null;
                switch ($cur) {
                    case 'AND':
                    case '+':
                        $key = 'AND';
                    case 'OR':
                    case '|':
                        $key = $key ?: 'OR';
                    case 'NOT':
                    case '-':
                    case '!':
                        $key = $key ?: 'NOT';

                        if (! $keyword) {
                            $keyword = true;
                        } elseif (empty($words)) {
                            $error = 'Logical operator at the beginning of the search (sub)query: \'%s\'';
                        } else {
                            $error = 'Logical operators follow one after another: \'%s\'';
                        }

                        $words[] = $key;

                        break;
                    case '(':
                        $stack[] = [$words, $keyword, $count];
                        $words   = [];
                        $keyword = true;
                        $count   = 0;

                        break;
                    case ')':
                        if (! $count) {
                            $error = 'Empty subquery: \'%s\'';
                        } elseif ($keyword) {
                            $error = 'Logical operator at the end of the search subquery: \'%s\'';
                        }
                        if (empty($stack)) {
                            $error = 'The order of brackets is broken: \'%s\'';
                        } else {
                            $temp = $words;
                            list($words, $keyword, $count) = \array_pop($stack);

                            if (! $keyword) {
                                $words[] = 'AND';
                            }

                            $words[] = $temp;
                            $keyword = false;
                            ++$count;
                        }

                        break;
                    default:
                        $cur    = \mb_strtolower($cur, 'UTF-8');
                        $cur    = $this->model->cleanText($cur); //????
                        $temp   = [];
                        $countT = 0;

                        foreach (\explode(' ', $cur) as $word) {
                            $word = $this->model->word($word);

                            if (null === $word) {
                                continue;
                            }
                            if (! empty($temp)) {
                                $temp[] = 'AND';
                            }
                            if ($this->model->isCJKWord($word)) {
                                $temp[] = ['type' => 'CJK', 'word' => $word];
                            } elseif (\rtrim($word, '?*') === $word) {
                                $temp[] = $word . '*'; //????
                            } else {
                                $temp[] = $word;
                            }

                            ++$countT;
                        }

                        if ($countT) {
                            if (! $keyword) {
                                $words[] = 'AND';
                            }
                            if (
                                1 === $countT
                                || 'AND' === \end($words)
                            ) {
                                $words  = \array_merge($words, $temp);
                                $count += $countT;
                            } else {
                                $words[] = $temp;
                                ++$count;
                            }

                            $keyword = false;
                        }

                        break;
                }
            }
            $quotes  = true;
        }

        if (! $count) {
            $error = 'There is no word for search: \'%s\'';
        } elseif ($keyword) {
            $error = 'Logical operator at the end of the search query: \'%s\'';
        } elseif (! empty($stack)) {
            $error = 'The order of brackets is broken: \'%s\'';
        }

        $this->model->queryError = $error;
        $this->model->queryWords = $words;
        $this->model->queryText  = $this->queryText($words);

        return null === $error;
    }

    /**
     * Восстановление текста запроса по массиву слов
     */
    protected function queryText(array $words): string
    {
        $space  = '';
        $result = '';

        foreach ($words as $word) {
            if (
                isset($word['type'])
                && 'CJK' === $word['type']
            ) {
                $word = '"' . $word['word'] . '"';
            } elseif (\is_array($word)) {
                $word = '(' . $this->queryText($word) . ')';
            }

            $result .= $space . $word;
            $space   = ' ';
        }

        return $result;
    }
}
