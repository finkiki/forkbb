<?php

namespace ForkBB\Models\Report;

use ForkBB\Models\DataModel;
use ForkBB\Models\Post\Model as Post;
use ForkBB\Models\User\Model as User;
use RuntimeException;

class Model extends DataModel
{
    /**
     * Устанавливает автора
     *
     * @param User $user
     *
     * @throws RuntimeException
     */
    protected function setauthor(User $user): void
    {
        if ($user->isGuest) {
            throw new RuntimeException('Bad author');
        }

        $this->reported_by = $user->id;
    }

    /**
     * Автор репорта
     *
     * @throws RuntimeException
     *
     * @return User
     */
    protected function getauthor(): User
    {
        if ($this->reported_by < 1) {
            throw new RuntimeException('No author data');
        }

        $user = $this->c->users->load($this->reported_by);

        if (! $user instanceof User || $user->isGuest) {
            $user = $this->c->users->create();

            $user->__id       = $this->reported_by;
            $user->__username = 'User #' . $this->reported_by;
        }

        return $user;
    }

    /**
     * Устанавливает пост
     *
     * @param Post $post
     *
     * @throws RuntimeException
     */
    protected function setpost(Post $post): void
    {
        if ($post->id < 1) {
            throw new RuntimeException('Bad post');
        }

        $this->post_id = $post->id;
    }

    /**
     * Пост
     *
     * @throws RuntimeException
     *
     * @return null|Post
     */
    protected function getpost(): ?Post
    {
        if ($this->post_id < 1) {
            throw new RuntimeException('No post data');
        }

        return $this->c->posts->load($this->post_id);
    }
}