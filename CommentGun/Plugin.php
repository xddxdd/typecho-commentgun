<?php
/**
 * 基于 Mailgun 的评论邮件提醒插件，由 CommentGun 改成，原作者 Byends Upd. (http://www.byends.com) & DEFE (http://defe.me)
 *
 * @package CommentGun
 * @author Lan Tian
 * @version 1.0.0
 * @link https://lantian.pub
 * @oriAuthor Byends Upd. (http://www.byends.com) & DEFE (http://defe.me)
 *
 */
class CommentGun_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        if (!function_exists('curl_version')) {
            throw new Typecho_Plugin_Exception(_t('对不起, 您的主机不支持 php-curl 扩展, 无法正常使用此功能'));
        }
        
        Typecho_Plugin::factory('Widget_Feedback')->finishComment = array('CommentGun_Plugin', 'parseComment');
        Typecho_Plugin::factory('Widget_Comments_Edit')->finishComment = array('CommentGun_Plugin', 'parseComment');

        return _t('请设置 API 信息，以使插件正常使用！');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {}

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $apikey = new Typecho_Widget_Helper_Form_Element_Text('apikey', NULL, 'key-',
                _t('API KEY'), _t('请填写 API KEY'));
        $form->addInput($apikey->addRule('required', _t('必须填写一个 API KEY')));

        $apidomain = new Typecho_Widget_Helper_Form_Element_Text('apidomain', NULL, '',
                _t('API 域名'), _t('请填写发信域名'));
        $form->addInput($apidomain->addRule('required', _t('必须填写发信域名')));

        $mail = new Typecho_Widget_Helper_Form_Element_Text('mail', NULL, NULL,
                _t('接收邮件的地址'),_t('接收邮件的地址，如为空则使用文章作者个人设置中的邮件地址！'));
        $form->addInput($mail->addRule('email', _t('请填写正确的邮件地址！')));

        $contactme = new Typecho_Widget_Helper_Form_Element_Text('contactme', NULL, NULL,
                _t('模板中“联系我”的邮件地址'),_t('联系我用的邮件地址,如为空则使用文章作者个人设置中的邮件地址！'));
        $form->addInput($contactme->addRule('email', _t('请填写正确的邮件地址！')));

        $status = new Typecho_Widget_Helper_Form_Element_Checkbox('status',
                array('approved' => '提醒已通过评论',
                        'waiting' => '提醒待审核评论',
                        'spam' => '提醒垃圾评论'),
                array('approved', 'waiting'), '提醒设置',_t('该选项仅针对博主，访客只发送已通过的评论。'));
        $form->addInput($status);

        $other = new Typecho_Widget_Helper_Form_Element_Checkbox('other',
                array('to_owner' => '有评论及回复时，发邮件通知博主。',
                    'to_guest' => '评论被回复时，发邮件通知评论者。',
                    'to_me'=>'自己回复自己的评论时，发邮件通知。(同时针对博主和访客)'),
                array('to_owner','to_guest'), '其他设置','');
        $form->addInput($other->multiMode());

        $titleForOwner = new Typecho_Widget_Helper_Form_Element_Text('titleForOwner',null,"《{title}》一文有新的评论",
                _t('博主接收邮件标题'));
        $form->addInput($titleForOwner->addRule('required', _t('博主接收邮件标题 不能为空')));

        $titleForGuest = new Typecho_Widget_Helper_Form_Element_Text('titleForGuest',null,"您在《{title}》的评论有了回复",
                _t('访客接收邮件标题'));
        $form->addInput($titleForGuest->addRule('required', _t('访客接收邮件标题 不能为空')));
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 获取邮件内容
     *
     * @access public
     * @param $comment 调用参数
     * @return void
     */
    public static function parseComment($comment)
    {
        $db = Typecho_Db::get();
        $user = Typecho_Widget::widget('Widget_User');
        $options = Typecho_Widget::widget('Widget_Options');
        $cfg = $options->plugin('CommentGun');
        $email = array(
            'siteTitle' => $options->title,
            'timezone'  => $options->timezone,
            'cid'       => $comment->cid,
            'coid'      => $comment->coid,
            'created'   => $comment->created,
            'author'    => $comment->author,
            'authorId'  => $comment->authorId,
            'ownerId'   => $comment->ownerId,
            'mail'      => $comment->mail,
            'ip'        => $comment->ip,
            'title'     => $comment->title,
            'text'      => $comment->text,
            'permalink' => $comment->permalink,
            'status'    => $comment->status,
            'parent'    => $comment->parent,
            'manage'    => $options->siteUrl . "admin/manage-comments.php",
            'from' => $cfg->user,
            'titleForOwner' => $cfg->titleForOwner,
            'titleForGuest' => $cfg->titleForGuest,
        );
        //验证博主是否接收自己的邮件
        $toMe = in_array('to_me', $cfg->other) && $email['ownerId'] == $email['authorId'];

        //向博主发信
        if (in_array($email['status'], $cfg->status) && in_array('to_owner', $cfg->other)
            && ( $toMe || $email['ownerId'] != $email['authorId']) && 0 == $email['parent'] ) {
            if (empty($cfg->mail)) {
                Typecho_Widget::widget('Widget_Users_Author@temp' . $email['cid'], array('uid' => $email['ownerId']))->to($user);
                $email['to'] = $user->mail;
            } else {
                $email['to'] = $cfg->mail;
            }
            self::sendMail(self::authorMail($email));
        }
        
        //向访客发信
        if (0 != $email['parent']
            && 'approved' == $email['status']
            && in_array('to_guest', $cfg->other)) {
            //如果联系我的邮件地址为空，则使用文章作者的邮件地址
            if (empty($email['contactme'])) {
                if (!isset($user) || !$user) {
                    Typecho_Widget::widget('Widget_Users_Author@temp' . $email['cid'], array('uid' => $email['ownerId']))->to($user);
                }
                $email['contactme'] = $user->mail;
            } else {
                $email['contactme'] = $cfg->contactme;
            }

            $original = $db->fetchRow($db->select('author', 'mail', 'text')
                                                       ->from('table.comments')
                                                       ->where('coid = ?', $email['parent']));

            if (in_array('to_me', $cfg->other) 
                || $email['mail'] != $original['mail']) {
                $email['to']             = $original['mail'];
                $email['originalText']   = $original['text'];
                $email['originalAuthor'] = $original['author'];
                self::sendMail(self::guestMail($email));
            }
        }
    }

    /**
     * 作者邮件信息
     * @return $email
     */
    public function authorMail($email)
    {
        $email['toName'] = $email['siteTitle'];
        $date = new Typecho_Date($email['created']);
        $time = $date->format('Y-m-d H:i:s');
        $status = array(
            "approved" => '通过',
            "waiting"  => '待审',
            "spam"     => '垃圾'
        );
        $search  = array(
            '{siteTitle}',
            '{title}',
            '{author}',
            '{ip}',
            '{mail}',
            '{permalink}',
            '{manage}',
            '{text}',
            '{time}',
            '{status}'
        );
        $replace = array(
            $email['siteTitle'],
            $email['title'],
            $email['author'],
            $email['ip'],
            $email['mail'],
            $email['permalink'],
            $email['manage'],
            $email['text'],
            $time,
            $status[$email['status']]
        );

        $email['msgHtml'] = str_replace($search, $replace, self::getTemplate('owner'));
        $email['subject'] = str_replace($search, $replace, $email['titleForOwner']);
        $email['altBody'] = "作者：".$email['author']."\r\n链接：".$email['permalink']."\r\n评论：\r\n".$email['text'];

        return $email;
    }

    /**
     * 访问邮件信息
     * @return $email
     */
    public function guestMail($email)
    {
        $email['toName']= $email['originalAuthor'] ? $email['originalAuthor'] : $email['siteTitle'];
        $date    = new Typecho_Date($email['created']);
        $time    = $date->format('Y-m-d H:i:s');
        $search  = array(
            '{siteTitle}',
            '{title}',
            '{author_p}',
            '{author}',
            '{permalink}',
            '{text}',
            '{contactme}',
            '{text_p}',
            '{time}'
        );
        $replace = array(
            $email['siteTitle'],
            $email['title'],
            $email['originalAuthor'],
            $email['author'],
            $email['permalink'],
            $email['text'],
            $email['contactme'],
            $email['originalText'],
            $time
        );

        $email['msgHtml'] = str_replace($search, $replace, self::getTemplate('guest'));
        $email['subject'] = str_replace($search, $replace, $email['titleForGuest']);
        $email['altBody'] = "作者：".$email['author']."\r\n链接：".$email['permalink']."\r\n评论：\r\n".$email['text'];

        return $email;
    }

    /*
     * 发送邮件
     */
    public function sendMail($email)
    {
        $cfg = Typecho_Widget::widget('Widget_Options')->plugin('CommentGun');
        $apiAddress = 'https://api.mailgun.net/v3/' . $cfg->apidomain . '/messages';
        $data = array(
            'from' => 'noreply@' . $cfg->apidomain,
            'to' => $email['toName'] . " <" . $email['to'] . ">",
            'subject' => $email['subject'],
            'html' => $email['msgHtml']
        );

        $ch = curl_init($apiAddress);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_USERPWD, 'api:' . $cfg->apikey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function getTemplate($template = 'owner')
    {
        $filename = __DIR__ . '/' . $template . '.html';

        if (!file_exists($filename)) {
           throw new Typecho_Widget_Exception('模板文件' . $template . '不存在', 404);
        }

        return file_get_contents($filename);
    }

}
