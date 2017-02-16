<?php

namespace ForkBB\Models;

use ForkBB\Core\AbstractModel;
use R2\DependencyInjection\ContainerInterface;
use RuntimeException;

class User extends AbstractModel
{
    /**
     * Контейнер
     * @var ContainerInterface
     */
    protected $c;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var UserCookie
     */
    protected $userCookie;

    /**
     * @var DB
     */
    protected $db;

    /**
     * Время
     * @var int
     */
    protected $now;

    /**
     * Конструктор
     */
    public function __construct(array $data, ContainerInterface $container)
    {
        $this->now = time();
        $this->c = $container;
        $this->config = $container->get('config');
        $this->userCookie = $container->get('UserCookie');
        $this->db = $container->get('DB');
        parent::__construct($data);
    }

    /**
     * Выполняется до конструктора родителя
     */
    protected function beforeConstruct(array $data)
    {
        return $data;
    }

    protected function getIsGuest()
    {
        return $this->id < 2 || empty($this->gId) || $this->gId == PUN_GUEST;
    }

    protected function getIsAdmin()
    {
        return $this->gId == PUN_ADMIN;
    }

    protected function getIsAdmMod()
    {
        return $this->gId == PUN_ADMIN || $this->gModerator == '1';
    }

    protected function getLogged()
    {
        return empty($this->data['logged']) ? $this->now : $this->data['logged'];
    }

    protected function getIsLogged()
    {
        return ! empty($this->data['logged']);
    }

    protected function getLanguage()
    {
        if ($this->isGuest
            || ! file_exists($this->c->getParameter('DIR_LANG') . '/' . $this->data['language'] . '/common.po')
        ) {
            return $this->config['o_default_lang'];
        } else {
            return $this->data['language'];
        }
    }

    protected function getStyle()
    {
        if ($this->isGuest
//???            || ! file_exists($this->c->getParameter('DIR_LANG') . '/' . $this->data['language'])
        ) {
            return $this->config['o_default_style'];
        } else {
            return $this->data['style'];
        }
    }

    /**
     * Выход
     */
    public function logout()
    {
        if ($this->isGuest) {
            return;
        }

        $this->userCookie->deleteUserCookie();
        $this->c->get('Online')->delete($this);
        // Update last_visit (make sure there's something to update it with)
        if ($this->isLogged) {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET last_visit='.$this->logged.' WHERE id='.$this->id) or error('Unable to update user visit data', __FILE__, __LINE__, $this->db->error());
        }
    }

    /**
     * Вход
     * @param string $name
     * @param string $password
     * @param bool $save
     * @return mixed
     */
    public function login($name, $password, $save)
    {
        $result = $this->db->query('SELECT u.id, u.group_id, u.username, u.password, u.registration_ip, g.g_moderator FROM '.$this->db->prefix.'users AS u LEFT JOIN '.$this->db->prefix.'groups AS g ON u.group_id=g.g_id WHERE u.username=\''.$this->db->escape($name).'\'') or error('Unable to fetch user info', __FILE__, __LINE__, $this->db->error());
        $user = $this->db->fetch_assoc($result);
        $this->db->free_result($result);

        if (empty($user['id'])) {
            return false;
        }

        $authorized = false;
        // For FluxBB by Visman 1.5.10.74 and above
        if (strlen($user['password']) == 40) {
            if (hash_equals($user['password'], sha1($password . $this->c->getParameter('SALT1')))) {
                $authorized = true;

                $user['password'] = password_hash($password, PASSWORD_DEFAULT);
                $this->db->query('UPDATE '.$this->db->prefix.'users SET password=\''.$this->db->escape($user['password']).'\' WHERE id='.$user['id']) or error('Unable to update user password', __FILE__, __LINE__, $this->db->error());
            }
        } else {
            $authorized = password_verify($password, $user['password']);
        }

        if (! $authorized) {
            return false;
        }

        // Update the status if this is the first time the user logged in
        if ($user['group_id'] == PUN_UNVERIFIED)
        {
            $this->db->query('UPDATE '.$this->db->prefix.'users SET group_id='.$this->config['o_default_user_group'].' WHERE id='.$user['id']) or error('Unable to update user status', __FILE__, __LINE__, $this->db->error());

            $this->c->get('users_info update');
        }

        // перезаписываем ip админа и модератора - Visman
        if ($this->config['o_check_ip'] == '1' && $user['registration_ip'] != $this->ip)
        {
            if ($user['g_id'] == PUN_ADMIN || $user['g_moderator'] == '1')
                $this->db->query('UPDATE '.$this->db->prefix.'users SET registration_ip=\''.$this->db->escape($this->ip).'\' WHERE id='.$user['id']) or error('Unable to update user IP', __FILE__, __LINE__, $this->db->error());
        }

        $this->c->get('Online')->delete($this);

        $this->c->get('UserCookie')->setUserCookie($user['id'], $user['password'], $save);

        return $user['id'];
    }
}
