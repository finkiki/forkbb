<?php

namespace ForkBB\Models\Pages;

use ForkBB\Core\Validator;
use ForkBB\Core\Exceptions\MailException;
use ForkBB\Models\Page;
use ForkBB\Models\User\Model as User;

class Register extends Page
{
    /**
     * Регистрация
     *
     * @return Page
     */
    public function reg()
    {
        $this->c->Lang->load('register');

        $v = $this->c->Validator->reset()
            ->addValidators([
                'check_email'    => [$this, 'vCheckEmail'],
                'check_username' => [$this->c->Validators, 'vCheckUsername'],
            ])->addRules([
                'token'    => 'token:RegisterForm',
                'agree'    => 'required|token:Register',
                'on'       => 'integer',
                'email'    => 'required_with:on|string:trim,lower|email|check_email',
                'username' => 'required_with:on|string:trim,spaces|min:2|max:25|login|check_username',
                'password' => 'required_with:on|string|min:16|password',
            ])->addAliases([
                'email'    => 'Email',
                'username' => 'Username',
                'password' => 'Passphrase',
            ])->addMessages([
                'agree.required'    => ['cancel', 'cancel'],
                'agree.token'       => [\ForkBB\__('Bad agree', $this->c->Router->link('Register')), 'w'],
                'password.password' => 'Pass format',
                'username.login'    => 'Login format',
            ]);

        // завершение регистрации
        if ($v->validation($_POST) && 1 === $v->on) {
            return $this->regEnd($v);
        }

        $this->fIswev = $v->getErrors();

        // нет согласия с правилами
        if (isset($this->fIswev['cancel'])) {
            return $this->c->Redirect->page('Index')->message('Reg cancel redirect');
        }

        $this->fIndex     = 'register';
        $this->nameTpl    = 'register';
        $this->onlinePos  = 'register';
        $this->titles     = \ForkBB\__('Register');
        $this->robots     = 'noindex';
        $this->formAction = $this->c->Router->link('RegisterForm');
        $this->formToken  = $this->c->Csrf->create('RegisterForm');
        $this->agree      = $v->agree;
        $this->on         = '1';
        $this->email      = $v->email;
        $this->username   = $v->username;

        return $this;
    }

    /**
     * Дополнительная проверка email
     *
     * @param Validator $v
     * @param string $email
     *
     * @return string
     */
    public function vCheckEmail(Validator $v, $email)
    {
        // email забанен
        if ($this->c->bans->isBanned($this->c->users->create(['email' => $email])) > 0) {
            $v->addError('Banned email');
        // найден хотя бы 1 юзер с таким же email
        } elseif (empty($v->getErrors()) && 0 !== $this->c->users->load($email, 'email')) {
            $v->addError('Dupe email');
        }
        return $email;
    }

    /**
     * Завершение регистрации
     *
     * @param Validator $v
     *
     * @return Page
     */
    protected function regEnd(Validator $v)
    {
        if ('1' == $this->c->config->o_regs_verify) {
            $groupId = 0;
            $key = 'w' . $this->c->Secury->randomPass(79);
        } else {
            $groupId = $this->c->config->o_default_user_group;
            $key = null;
        }

        $user = $this->c->users->create();

        $user->username        = $v->username;
        $user->password        = password_hash($v->password, PASSWORD_DEFAULT);
        $user->group_id        = $groupId;
        $user->email           = $v->email;
        $user->email_confirmed = 0;
        $user->activate_string = $key;
        $user->u_mark_all_read = \time();
        $user->email_setting   = $this->c->config->o_default_email_setting;
        $user->timezone        = $this->c->config->o_default_timezone;
        $user->dst             = $this->c->config->o_default_dst;
        $user->language        = $user->language; //????
        $user->style           = $user->style;    //????
        $user->registered      = \time();
        $user->registration_ip = $this->user->ip;

        $newUserId = $this->c->users->insert($user);

        // обновление статистики по пользователям
        if ('1' != $this->c->config->o_regs_verify) {
            $this->c->Cache->delete('stats');
        }

        // уведомление о регистрации
        if ('1' == $this->c->config->o_regs_report && '' != $this->c->config->o_mailing_list) {
            $tplData = [
                'fTitle' => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                'username' => $v->username,
                'userLink' => $this->c->Router->link('User', ['id' => $newUserId, 'name' => $v->username]),
            ];

            try {
                $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->c->config->o_default_lang)
                    ->setTo($this->c->config->o_mailing_list)
                    ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                    ->setTpl('new_user.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
            //????
            }
        }

        $this->c->Lang->load('register');

        // отправка письма активации аккаунта
        if ('1' == $this->c->config->o_regs_verify) {
            $hash = $this->c->Secury->hash($newUserId . $key);
            $link = $this->c->Router->link('RegActivate', ['id' => $newUserId, 'key' => $key, 'hash' => $hash]);
            $tplData = [
                'fTitle' => $this->c->config->o_board_title,
                'fRootLink' => $this->c->Router->link('Index'),
                'fMailer' => \ForkBB\__('Mailer', $this->c->config->o_board_title),
                'username' => $v->username,
                'link' => $link,
            ];

            try {
                $isSent = $this->c->Mail
                    ->reset()
                    ->setFolder($this->c->DIR_LANG)
                    ->setLanguage($this->user->language)
                    ->setTo($v->email)
                    ->setFrom($this->c->config->o_webmaster_email, \ForkBB\__('Mailer', $this->c->config->o_board_title))
                    ->setTpl('welcome.tpl', $tplData)
                    ->send();
            } catch (MailException $e) {
                $isSent = false;
            }

            // письмо активации аккаунта отправлено
            if ($isSent) {
                return $this->c->Message->message(\ForkBB\__('Reg email', $this->c->config->o_admin_email), false, 200);
            // форма сброса пароля
            } else {
                $auth = $this->c->Auth;
                $auth->fIswev = ['w' => [\ForkBB\__('Error welcom mail', $this->c->config->o_admin_email)]];
                return $auth->forget(['_email' => $v->email], 'GET');
            }
        // форма логина
        } else {
            $auth = $this->c->Auth;
            $auth->fIswev = ['s' => [\ForkBB\__('Reg complete')]];
            return $auth->login(['_username' => $v->username], 'GET');
        }
    }

    /**
     * Активация аккаунта
     *
     * @param array $args
     *
     * @return Page
     */
    public function activate(array $args)
    {
        if (! \hash_equals($args['hash'], $this->c->Secury->hash($args['id'] . $args['key']))
            || ! ($user = $this->c->users->load($args['id'])) instanceof User
            || empty($user->activate_string)
            || 'w' !== $user->activate_string{0}
            || ! \hash_equals($user->activate_string, $args['key'])
        ) {
            return $this->c->Message->message('Bad request', false);
        }

        $user->group_id        = $this->c->config->o_default_user_group;
        $user->email_confirmed = 1;
        $user->activate_string = null;
        $this->c->users->update($user);

        $this->c->Cache->delete('stats');

        $this->c->Lang->load('register');

        $auth = $this->c->Auth;
        $auth->fIswev = ['s' => [\ForkBB\__('Reg complete')]];
        return $auth->login(['_username' => $user->username], 'GET');
    }
}
