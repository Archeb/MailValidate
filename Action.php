<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'lib/PHPMailer.php';
require_once 'lib/SMTP.php';
require_once 'lib/Exception.php';

class MailValidate_Action extends Typecho_Widget implements Widget_Interface_Do
{
    /** @var  数据操作对象 */
    private $_db;
    
    /** @var  插件根目录 */
    private $_dir;
    
    /** @var  插件配置信息 */
    private $_cfg;
    
    /** @var  系统配置信息 */
    private $_options;
    
    /** @var bool 是否记录日志 */
    private $_isMailLog = false;
    
    /** @var 当前登录用户 */
    private $_user;
    
    /** @var  邮件内容信息 */
    private $_email;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);    
    }

    public function init()
    {
        $this->_dir = dirname(__FILE__);
        $this->_db = Typecho_Db::get();
        $this->_user = $this->widget('Widget_User');
        $this->_options = $this->widget('Widget_Options');
        $this->_cfg = Helper::options()->plugin('MailValidate');
        // 【修复】必须初始化为一个标准对象，否则 PHP 8 下直接赋值属性会报错
        $this->_email = new \stdClass();
    }

    public function execute() {
        return;
    }

    /*
     * 发送邮件
     */
    public function sendMail()
    {
        /** 载入邮件组件 */
        $mailer = new PHPMailer();
        $mailer->CharSet = 'UTF-8';
        $mailer->Encoding = 'base64';

        //选择发信模式
        switch ($this->_cfg->mode)
        {
            case 'mail':
                break;
            case 'sendmail':
                $mailer->IsSendmail();
                break;
            case 'smtp':
                $mailer->IsSMTP();

                // 【修复】确保从配置中读取的 validate 项是数组，防止复选框未选中时为 null 导致 in_array 报错
                $validateConfig = is_array($this->_cfg->validate) ? $this->_cfg->validate : [];

                if (in_array('validate', $validateConfig)) {
                    $mailer->SMTPAuth = true;
                }

                if (in_array('ssl', $validateConfig)) {
                    $mailer->SMTPSecure = "ssl";
                } else if (in_array('tls', $validateConfig)) {
                    $mailer->SMTPSecure = "tls";
                }

                $mailer->Host     = $this->_cfg->host;
                $mailer->Port     = $this->_cfg->port;
                $mailer->Username = $this->_cfg->user;
                $mailer->Password = $this->_cfg->pass;

                break;
        }

        $mailer->SetFrom($this->_email->from, $this->_email->fromName);
        $mailer->AddReplyTo($this->_email->to, $this->_email->toName);
        $mailer->Subject = $this->_email->subject;
        $mailer->AltBody = $this->_email->altBody;
        $mailer->MsgHTML($this->_email->msgHtml);
        $mailer->AddAddress($this->_email->to, $this->_email->toName);

        if ($result = $mailer->Send()) {
            // $this->mailLog(); // 原代码这里无此方法，不影响核心逻辑
            $result = true;
        } else {
            // $this->mailLog(false, $mailer->ErrorInfo . "\r\n");
            $result = $mailer->ErrorInfo;
        }
        
        $mailer->ClearAddresses();
        $mailer->ClearReplyTos();

        return $result;
    }

    public function action(){
        $this->init();
        $token = $this->request->token;
        if($token){
            try {
                $row = $this->_db->fetchRow($this->_db->select('validate_state')->from('table.users')->where('validate_token = ?', $token));
                // 【修复】弱类型比较，因为数据库查出来可能是字符串 '1' 也可能是数字 1
                if($row && $row['validate_state'] == "1"){
                    $this->_db->query($this->_db->update('table.users')->rows(array('validate_state' => 2))->where('validate_token = ?', $token));
                    $group = $this->_db->fetchRow($this->_db->select('group')->from('table.users')->where('validate_token = ?', $token));
                    if($group && $group['group'] === "subscriber"){
                        $this->_db->query($this->_db->update('table.users')->rows(array('group' => "contributor"))->where('validate_token = ?', $token));
                    }
                    echo(file_get_contents($this->_dir."/success.html"));
                }else{
                    echo(file_get_contents($this->_dir."/fail.html"));
                }
            } catch (Exception $ex) {
               echo $ex->getMessage(); 
            }
        }  else {
            echo(file_get_contents($this->_dir."/fail.html"));
        }
    }

    public function send(){
        $this->init();
        if(!$this->_user->mail){
            $this->widget('Widget_Notice')->set("邮件发送失败，没有找到用户邮箱",'notice');
            $this->response->goBack();
        }else{
            $this->_email->from = $this->_cfg->user;
            $this->_email->fromName = $this->_cfg->fromName ? $this->_cfg->fromName : $this->_options->title;
            $this->_email->to = $this->_user->mail;
            $this->_email->toName = $this->_user->screenName;
            $this->_email->subject = $this->_cfg->titleForGuest;
            
            //生成token：md5(mail+time+随机数)
            $token=md5($this->_user->mail . time() . $this->_user->mail . rand());
            $this->_db->query($this->_db->update('table.users')->rows(array('validate_token' => $token))->where('uid = ?', $this->_user->uid));
            
            $mailcontent=file_get_contents($this->_dir."/mail.html");
            $keys=array(
                '%sitename%'=>$this->_options->title,
                '%username%'=>$this->_user->screenName,
                '%verifyurl%'=>$this->_options->siteUrl."MailValidate/verify?token=".$token, // 修复URL拼接问题
                '%useravatar%'=>md5(strtolower(trim($this->_user->mail))) // 修复 Gravatar 获取逻辑
            );
            $mailcontent=strtr($mailcontent,$keys);

            $this->_email->altBody = $mailcontent;
            $this->_email->msgHtml = $mailcontent;
            $result = $this->sendMail();
            
            if ($result === true) {
                $this->_db->query($this->_db->update('table.users')->rows(array('validate_state' => 1))->where('uid = ?', $this->_user->uid));
                $this->widget('Widget_Notice')->set(_t('邮件发送成功'), 'success');
            } else {
                $this->widget('Widget_Notice')->set(_t('邮件发送失败：' . $result), 'notice');
            }
    
            $this->response->goBack();
        }
    }
}
?>