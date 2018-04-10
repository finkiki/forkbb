<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Auth extends Page
{
    /**
     * Выход пользователя
     *
     * @param array $args
     *
     * @return Page
     */
    public function logout($args)
    {
        if (! $this->c->Csrf->verify($args['token'], 'Logout', $args)) {
            return $this->c->Redirect->page('Index')->message('Bad token');
        }

        $this->c->Cookie->deleteUser();
        $this->c->Online->delete($this->user);
        $this->c->users->updateLastVisit($this->user);

        $this->c->Lang->load('auth');
        return $this->c->Redirect->page('Index')->message('Logout redirect');
    }

    /**
     * Вход на форум
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function login(array $args, $method)
    {
        $this->c->Lang->load('auth');

        $v = null;
        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addValidators([
                    'login_process' => [$this, 'vLoginProcess'],
                ])->addRules([
                    'token'    => 'token:Login',
                    'redirect' => 'required|referer:Index',
                    'username' => 'required|string',
                    'password' => 'required|string|login_process',
                    'save'     => 'checkbox',
                ])->addAliases([
                    'username' => 'Username',
                    'password' => 'Passphrase',
                ]);

            if ($v->validation($_POST)) {
                return $this->c->Redirect->url($v->redirect)->message('Login redirect');
            }

            $this->fIswev = $v->getErrors();
        }

        $ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        $this->fIndex     = 'login';
        $this->nameTpl    = 'login';
        $this->onlinePos  = 'login';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Login');
        $this->formAction = $this->c->Router->link('Login');
        $this->formToken  = $this->c->Csrf->create('Login');
        $this->forgetLink = $this->c->Router->link('Forget');
        $this->regLink    = $this->c->config->o_regs_allow == '1' ? $this->c->Router->link('Register') : null;
        $this->username   = $v ? $v->username : (isset($args['_username']) ? $args['_username'] : '');
        $this->redirect   = $v ? $v->redirect : $this->c->Router->validate($ref, 'Index');
        $this->save       = $v ? $v->save : 1;

        return $this;
    }

    /**
     * Проверка по базе и вход
     *
     * @param Validator $v
     * @param string $password
     *
     * @return string
     */
    public function vLoginProcess(Validator $v, $password)
    {
        if (! empty($v->getErrors())) {
        } elseif (! ($user = $this->c->users->load($v->username, 'username')) instanceof User) {
            $v->addError('Wrong user/pass');
        } elseif ($user->isUnverified) {
            $v->addError('Account is not activated', 'w');
        } else {
            $authorized = false;
            $hash = $user->password;
            // For FluxBB by Visman 1.5.10.74 and above
            if (\strlen($hash) == 40) {
                if (\hash_equals($hash, sha1($password . $this->c->SALT1))) {
                    $hash = \password_hash($password, \PASSWORD_DEFAULT);
                    $user->password = $hash;
                    $authorized = true;
                }
            } else {
                $authorized = \password_verify($password, $hash);
            }
            // ошибка в пароле
            if (! $authorized) {
                $v->addError('Wrong user/pass');
            } else {
                // перезаписываем ip админа и модератора - Visman
                if ($user->isAdmMod
                    && $this->c->config->o_check_ip
                    && $user->registration_ip != $this->user->ip
                ) {
                    $user->registration_ip = $this->user->ip;
                }
                // сбросить запрос на смену кодовой фразы
                if (32 === \strlen($user->activate_string)) {
                    $user->activate_string = '';
                }
                // изменения юзера в базе
                $this->c->users->update($user);

                $this->c->Online->delete($this->user);
                $this->c->Cookie->setUser($user, (bool) $v->save);
            }
        }

        return $password;
    }

    /**
     * Запрос на смену кодовой фразы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function forget(array $args, $method)
    {
        $this->c->Lang->load('auth');

        $v = null;

        if ('POST' === $method) {
            $tmpUser = $this->c->users->create();

            $v = $this->c->Validator->reset()
                ->addValidators([
                    'check_email' => [$this->c->Validators, 'vCheckEmail'],
                ])->addRules([
                    'token' => 'token:Forget',
                    'email' => 'required|string:trim,lower|email|check_email:exists,flood',
                ])->addAliases([
                ])->addMessages([
                    'email.email' => 'Invalid email',
                ])->addArguments([
                    'email.check_email' => $tmpUser, // сюда идет возрат данных по найденному пользователю
                ]);

            if ($v->validation($_POST)) {
                $key  = $this->c->Secury->randomPass(32);
                $hash = $this->c->Secury->hash($v->email . $key);
                $link = $this->c->Router->link('ChangePassword', ['email' => $v->email, 'key' => $key, 'hash' => $hash]);
                $tplData = [
                    'fRootLink' => $this->c->Router->link('Index'),
                    'fMailer'   => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                    'username'  => $tmpUser->username,
                    'link'      => $link,
                ];

                try {
                    $isSent = $this->c->Mail
                        ->reset()
                        ->setFolder($this->c->DIR_LANG)
                        ->setLanguage($tmpUser->language)
                        ->setTo($v->email, $tmpUser->username)
                        ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                        ->setTpl('passphrase_reset.tpl', $tplData)
                        ->send();
                } catch (MailException $e) {
                    $isSent = false;
                }

                if ($isSent) {
                    $tmpUser->activate_string = $key;
                    $tmpUser->last_email_sent = \time();
                    $this->c->users->update($tmpUser);
                    return $this->c->Message->message(\ForkBB\__('Forget mail', $this->c->config->o_admin_email), false, 200);
                } else {
                    return $this->c->Message->message(\ForkBB\__('Error mail', $this->c->config->o_admin_email), true, 200);
                }
            }

            $this->fIswev = $v->getErrors();
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'passphrase_reset';
        $this->onlinePos  = 'passphrase_reset';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Passphrase reset');
        $this->formAction = $this->c->Router->link('Forget');
        $this->formToken  = $this->c->Csrf->create('Forget');
        $this->email      = $v ? $v->email : (isset($args['_email']) ? $args['_email'] : '');

        return $this;
    }

    /**
     * Смена кодовой фразы
     *
     * @param array $args
     * @param string $method
     *
     * @return Page
     */
    public function changePass(array $args, $method)
    {
        // что-то пошло не так
        if (! \hash_equals($args['hash'], $this->c->Secury->hash($args['email'] . $args['key']))
            || ! ($user = $this->c->users->load($args['email'], 'email')) instanceof User
            || empty($user->activate_string)
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $this->c->Lang->load('auth');

        if ('POST' === $method) {
            $v = $this->c->Validator->reset()
                ->addRules([
                    'token'     => 'token:ChangePassword',
                    'password'  => 'required|string|min:16|password',
                    'password2' => 'required|same:password',
                ])->addAliases([
                    'password'  => 'New pass',
                    'password2' => 'Confirm new pass',
                ])->addArguments([
                    'token' => $args,
                ])->addMessages([
                    'password.password'  => 'Pass format',
                    'password2.same'     => 'Pass not match',
                ]);

            if ($v->validation($_POST)) {
                $user->password        = \password_hash($v->password, PASSWORD_DEFAULT);
                $user->email_confirmed = 1;
                $user->activate_string = '';

                $this->c->users->update($user);

                $this->fIswev = ['s', \ForkBB\__('Pass updated')];
                return $this->login([], 'GET');
            }

            $this->fIswev = $v->getErrors();
        }
        // активация аккаунта (письмо активации не дошло, заказали восстановление)
        if ($user->isUnverified) {
            $user->group_id        = $this->c->config->o_default_user_group;
            $user->email_confirmed = 1;
            $this->c->users->update($user);
            $this->c->Cache->delete('stats');
            $this->fIswev = ['i', \ForkBB\__('Account activated')];
        }

        $this->fIndex     = 'login';
        $this->nameTpl    = 'change_passphrase';
        $this->onlinePos  = 'change_passphrase';
        $this->robots     = 'noindex';
        $this->titles     = \ForkBB\__('Passphrase reset');
        $this->formAction = $this->c->Router->link('ChangePassword', $args);
        $this->formToken  = $this->c->Csrf->create('ChangePassword', $args);

        return $this;
    }
}
