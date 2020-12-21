<?php
/**
 * This file is part of the ForkBB <https://github.com/forkbb>.
 *
 * @copyright (c) Visman <mio.visman@yandex.ru, https://github.com/MioVisman>
 * @license   The MIT License (MIT)
 */

declare(strict_types=1);

namespace ForkBB\Models\Forum;

use ForkBB\Models\Action;
use ForkBB\Models\DataModel;
use ForkBB\Models\Forum\Model as Forum;
use ForkBB\Models\User\Model as User;
use InvalidArgumentException;
use RuntimeException;

class Delete extends Action
{
    /**
     * Удаляет раздел(ы)
     */
    public function delete(DataModel ...$args): void
    {
        if (empty($args)) {
            throw new InvalidArgumentException('No arguments, expected User(s) or Forum(s)');
        }

        $uids    = [];
        $forums  = [];
        $all     = [];
        $isUser  = 0;
        $isForum = 0;

        foreach ($args as $arg) {
            if ($arg instanceof User) {
                if ($arg->isGuest) {
                    throw new RuntimeException('Guest can not be deleted');
                }

                $uids[$arg->id] = $arg->id;
                $isUser         = 1;
            } elseif ($arg instanceof Forum) {
                if (! $this->c->forums->get($arg->id) instanceof Forum) {
                    throw new RuntimeException('Forum unavailable');
                }

                $forums[$arg->id] = $arg;
                $all[$arg->id]    = true;
                $isForum          = 1;

                foreach (\array_keys($arg->descendants) as $id) { //???? а если не админ?
                    $all[$id] = true;
                }
            } else {
                throw new InvalidArgumentException('Expected User(s) or Forum(s)');
            }
        }

        if ($isUser + $isForum > 1) {
            throw new InvalidArgumentException('Expected only User(s) or Forum(s)');
        }

        if (\array_diff_key($all, $forums)) {
            throw new RuntimeException('Descendants should not be or they should be deleted too');
        }

        $this->c->topics->delete(...$args);

        if ($uids) {
            $vars = [
                ':users' => $uids,
            ];
            $query = 'DELETE
                FROM ::mark_of_forum
                WHERE uid IN (?ai:users)';

            $this->c->DB->exec($query, $vars);

            $query = 'UPDATE ::forums
                SET last_poster_id=1
                WHERE last_poster_id IN (?ai:users)';

            $this->c->DB->exec($query, $vars);
        }

        if ($forums) {
            $this->c->subscriptions->unsubscribe(...$forums);

            foreach ($forums as $forum) {
                $this->c->groups->Perm->reset($forum);
            }

            $vars = [
                ':forums' => \array_keys($forums),
            ];
            $query = 'DELETE
                FROM ::mark_of_forum
                WHERE fid IN (?ai:forums)';

            $this->c->DB->exec($query, $vars);

            $query = 'DELETE
                FROM ::forums
                WHERE id IN (?ai:forums)';

            $this->c->DB->exec($query, $vars);
        }
    }
}
