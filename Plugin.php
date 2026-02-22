<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MailValidate_Plugin implements Typecho_Plugin_Interface{

    public static function activate(){
        Helper::addRoute("MailValidateAction_Verify","/MailValidate/verify","MailValidate_Action",'action');
        Helper::addRoute("MailValidateAction_Send","/MailValidate/send","MailValidate_Action",'send');
        $db = Typecho_Db::get();
        
        $prefix = $db->getPrefix();
        $userRow = $db->fetchRow($db->select()->from('table.users'));
        
        // 【修复】检查查询结果是否为空以及类型安全
        if ($userRow && !array_key_exists('validate_state', $userRow)){
            $db->query('ALTER TABLE `'. $prefix .'users` ADD `validate_state` INT(1) DEFAULT 0;');
            $db->query('ALTER TABLE `'. $prefix .'users` ADD `validate_token` varchar(32) DEFAULT 0;');
        }
        Typecho_Plugin::factory('admin/menu.php')->navBar = array('MailValidate_Plugin', 'render');
    }
    
    public static function deactivate(){
        Helper::removeRoute('MailValidateAction_Verify');
        Helper::removeRoute('MailValidateAction_Send');
    }
    
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $mode= new Typecho_Widget_Helper_Form_Element_Radio('mode',
                array( 'smtp' => 'smtp',
                       'mail' => 'mail()',
                       'sendmail' => 'sendmail()'),
                'smtp', '发信方式');
        $form->addInput($mode);

        $host = new Typecho_Widget_Helper_Form_Element_Text('host', NULL, 'smtp.',
                _t('SMTP地址'), _t('请填写 SMTP 服务器地址 (如 smtp.qq.com)'));
        $form->addInput($host->addRule('required', _t('必须填写一个SMTP服务器地址')));

        $port = new Typecho_Widget_Helper_Form_Element_Text('port', NULL, '465',
                _t('SMTP端口'), _t('SMTP服务端口, SSL通常为465, 普通为25。'));
        $port->input->setAttribute('class', 'mini');
        $form->addInput($port->addRule('required', _t('必须填写SMTP服务端口'))
                ->addRule('isInteger', _t('端口号必须是纯数字')));

        $user = new Typecho_Widget_Helper_Form_Element_Text('user', NULL, NULL,
                _t('SMTP用户'),_t('SMTP服务验证用户名,一般为邮箱名如：youname@domain.com'));
        $form->addInput($user->addRule('required', _t('SMTP服务验证用户名')));

        $pass = new Typecho_Widget_Helper_Form_Element_Password('pass', NULL, NULL,
                _t('SMTP密码'), _t('邮箱的SMTP授权码或密码'));
        $form->addInput($pass->addRule('required', _t('SMTP服务验证密码')));

        $validate = new Typecho_Widget_Helper_Form_Element_Checkbox('validate',
                array('validate'=>'服务器需要验证',
                    'ssl'=>'ssl加密',
                    'tls'=>'tls加密'),
                array('validate', 'ssl'),'SMTP验证');
        $form->addInput($validate);
        
        $fromName = new Typecho_Widget_Helper_Form_Element_Text('fromName', NULL, NULL,
                _t('发件人名称'),_t('发件人名称，留空则使用博客标题'));
        $form->addInput($fromName);

        $titleForGuest = new Typecho_Widget_Helper_Form_Element_Text('titleForGuest',null,"请完成邮箱验证",
                _t('邮件标题'));
        $form->addInput($titleForGuest->addRule('required', _t('邮件标题 不能为空')));
    }

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    public static function render(){
        // 【修复】使用更新的方式获取当前用户
        $user = \Typecho\Widget::widget('Widget_User');
        $db = Typecho_Db::get(); 
        $row = $db->fetchRow($db->select('validate_state')->from('table.users')->where('uid = ?',  $user->uid));
        
        // 【修复】避免$row不存在时报错，使用弱类型判断
        if($row && isset($row['validate_state'])){
            if($row['validate_state'] == "0"){
                echo '<span class="message" target="_self" style="background-color:#ff4a4a;color:white;cursor:pointer;" onclick="window.location=\'/MailValidate/send\'">点此完成邮件验证以发表文章</span>';
            }else if($row['validate_state'] == "1"){
                echo '<span class="message" target="_self" style="background-color:#ff9b2e;color:white;cursor:pointer;" onclick="window.location=\'/MailValidate/send\'">点此重发验证邮件</span>';
            }
        }
    }
}