<?php
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
    private  $_email;
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
        require_once $this->_dir . '/lib/class.phpmailer.php';
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

                if (in_array('validate', $this->_cfg->validate)) {
                    $mailer->SMTPAuth = true;
                }

                if (in_array('ssl', $this->_cfg->validate)) {
                    $mailer->SMTPSecure = "ssl";
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
            $this->mailLog();
        } else {
            $this->mailLog(false, $mailer->ErrorInfo . "\r\n");
            $result = $mailer->ErrorInfo;
        }
        
        $mailer->ClearAddresses();
        $mailer->ClearReplyTos();

        return $result;
    }
    public function action(){
	$this->init();
        $token=$this->request->token;
        if($token){
            try {
                $row = $this->_db->fetchRow($this->_db->select('validate_state')->from('table.users')->where('validate_token = ?', $token));
                if($row['validate_state']==="1"){
                    $this->_db->query($this->_db->update('table.users')->rows(array('validate_state' => 2))->where('validate_token = ?', $token));
                    $group = $this->_db->fetchRow($this->_db->select('group')->from('table.users')->where('validate_token = ?', $token));
                    if($group['group']==="subscriber"){
                        $this->_db->query($this->_db->update('table.users')->rows(array('group' => "contributor"))->where('validate_token = ?', $token));
                    }
                    echo(file_get_contents($this->_dir."/success.html"));
                }else{
                    echo(file_get_contents($this->_dir."/fail.html"));
                }
            } catch (Exception $ex) {
               echo $ex->getCode(); 
            }
        }  else {
            echo(file_get_contents($this->_dir."/fail.html"));
        }
      
    }
    public function send(){
        $this->init();
        if(!$this->_user->mail){
            $this->widget('Widget_Notice')->set("邮件发送失败",'notice');
            $this->response->goBack();
        }else{
            $this->_email->from = $this->_cfg->user;
            $this->_email->fromName = $this->_cfg->fromName ? $this->_cfg->fromName : $this->_options->title;
            $this->_email->to = $this->_user->mail;
            $this->_email->toName = $this->_user->screenName;
            $this->_email->subject = $this->_cfg->titleForGuest;
            //生成token：md5(mail+time+随机数)
            $token=md5($this->_user->mail.time().$this->_user->mail.rand());
            $this->_db->query($this->_db->update('table.users')->rows(array('validate_token' => $token))->where('uid = ?', $this->_user->uid));
            $mailcontent=file_get_contents($this->_dir."/mail.html");
            $keys=array('%sitename%'=>$this->_options->title,'%username%'=>$this->_user->screenName,'%verifyurl%'=>"https://ero.ink/MailValidate/verify?token=".$token,'%useravatar%'=>md5($this->_user->mail));
            $mailcontent=strtr($mailcontent,$keys);
            $this->_email->altBody = $mailcontent;
            $this->_email->msgHtml = $mailcontent;
            $result = $this->sendMail();
            $this->_db->query($this->_db->update('table.users')->rows(array('validate_state' => 1))->where('uid = ?', $this->_user->uid));
            $this->widget('Widget_Notice')->set(true === $result ? _t('邮件发送成功') : _t('邮件发送失败：' . $result),
                true === $result ? 'success' : 'notice');
    
            $this->response->goBack();
        }
    }
}
?>
