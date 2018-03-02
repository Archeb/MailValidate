# MailValidate
Typecho Mail Validate Plugin
这是一个 Typecho 邮箱验证插件

用户完成邮箱验证后，会自动提升权限到贡献者，也就是可以发表文章但需要审核

### 安装说明
- 上传到/usr/plugins/ （包括文件夹）
- 控制台 - 插件 处启用 MailValidate
- 插件列表右边的设置 设置好SMTP
- Enjoy it!

### 使用说明
您可以在插件目录下找到mail.html 、 success.html 和 fail.html 三个文件

分别对应邮件内容、验证成功和验证失败三个模板

您可以在邮件内容中使用%username% 、 %sitename% 、%verifyurl% 和 %useravatar%（用户邮箱的MD5值）几个变量

如果您对邮件样式不满意或者背景图片链接失效 请自行更改.

### 版权信息
插件开发过程中参考了 [CommentToMail](https://github.com/byends/CommentToMail) 插件和 Like插件（点赞插件 原作者已找不到）

发布协议为MIT，欢迎随意使用修改转载，只需保留作者信息。
