<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

abstract class Profile extends Page
{
    /**
     * Инициализирует профиль
     *
     * @param string|int $id
     *
     * @return bool
     */
    protected function initProfile($id)
    {
        $this->curUser = $this->c->users->load((int) $id);

        if (! $this->curUser instanceof User
            || $this->curUser->isGuest
            || ($this->curUser->isUnverified && ! $this->user->isAdmMod)
        ) {
            return false;
        }

        $this->c->Lang->load('profile');

        $this->rules     = $this->c->ProfileRules->setUser($this->curUser);
        $this->robots    = 'noindex';
        $this->fIndex    = $this->rules->my ? 'profile' : 'userlist';
        $this->nameTpl   = 'profile';
        $this->onlinePos = 'profile-' . $this->curUser->id; // ????
        $this->title     = \ForkBB\__('%s\'s profile', $this->curUser->username);

        return true;
    }

    /**
     * Проверяет пароль на совпадение с текущим пользователем
     *
     * @param Validator $v
     * @param string $password
     *
     * @return string
     */
    public function vCheckPassword(Validator $v, $password)
    {
        if (! \password_verify($password, $this->user->password)) {
            $v->addError('Invalid passphrase');
        }

        return $password;
    }

    /**
     * Возвращает массив хлебных крошек
     * Заполняет массив титула страницы
     *
     * @param mixed $crumbs
     *
     * @return array
     */
    protected function crumbs(...$crumbs)
    {
        $crumbs[] = [$this->curUser->link, \ForkBB\__('User %s', $this->curUser->username)];
        $crumbs[] = [$this->c->Router->link('Userlist'), \ForkBB\__('User list')];

        return parent::crumbs(...$crumbs);
    }

    /**
     * Формирует массив кнопок
     *
     * @param string $type
     *
     * @return array
     */
    protected function btns($type)
    {
        $btns = [];
        if ($this->user->isAdmin && ! $this->rules->editProfile) {
            $btns['change-user-group'] = [
                $this->linkChangeGroup(),
                \ForkBB\__('Change user group'),
            ];
        }
        if ($this->rules->banUser) {
            $btns['ban-user'] = [
                $this->c->Router->link('AdminBansNew',  ['ids' => $this->curUser->id]),
                \ForkBB\__('Ban user'),
            ];
        }
        if ($this->rules->deleteUser) {
            $btns['delete-user'] = [
                $this->c->Router->link('AdminUsersAction',  ['action' => 'delete', 'ids' => $this->curUser->id]), // ????
                \ForkBB\__('Delete user'),
            ];
        }
        if ('edit' != $type && $this->rules->editProfile) {
            $btns['edit-profile'] = [
                $this->c->Router->link('EditUserProfile',  ['id' => $this->curUser->id]),
                \ForkBB\__('Edit '),
            ];
        }
        if ('view' != $type) {
            $btns['view-profile'] = [
                $this->curUser->link,
                \ForkBB\__('View '),
            ];
        }
        if ('config' != $type && $this->rules->editConfig) {
            $btns['edit-settings'] = [
                $this->c->Router->link('EditUserBoardConfig', ['id' => $this->curUser->id]),
                \ForkBB\__('Configure '),
            ];
        }
        return $btns;
    }

    /**
     * Формирует ссылку на изменение группы пользователя
     *
     * @return string
     */
    protected function linkChangeGroup()
    {
        return $this->c->Router->link('AdminUsersAction',  [
            'action' => 'change_group',
            'ids'    => $this->curUser->id,
            'token'  => $this->c->Csrf->create('AdminUsersAction', [
                'action' => 'change_group',
                'ids'    => $this->curUser->id,
            ]),
        ]);
    }
}
