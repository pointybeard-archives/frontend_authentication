Front End Authentication
------------------------

- **Version:** 1.0
- **Author:** Alistair Kearney (<alistair@symphony21.com>)
- **Build Date:** 25th Sept 2008
- **Requirements:** Latest Symphony 2 Beta ([See here for earliest compatible tree](http://github.com/symphony/symphony-2/tree/b8501ca60d6a4c3f9db1c26c40874da1de0d6748))


This extension is useful for protecting specific pages of your Symphony powered site. Based on either Sessions or Cookies


### INSTALLATION

** Note: The latest version can alway be grabbed with `git clone git://github.com/pointybeard/frontend_authentication.git`

1. Upload the 'frontend_authentication' folder in this archive to your Symphony 'extensions' folder.
2. Enable it by selecting the "Front End Authentication", choose Enable from the with-selected menu, then click Apply.
3. Visit the preferences area to begin configuration


### USAGE

- Create a new section with 1 field for the username and password
- Create a login page with a form resembling the following:

<pre>
	<code>
	&lt;form method="POST" action="">

		&lt;xsl:if test="//front-end-authentication/@status = 'invalid'">
			&lt;h1>Authentication Failed! Please check your details&lt;/h1>
		&lt;/xsl:if>

		&lt;xsl:if test="//front-end-authentication/@password-retrieval-email-status = 'sent'">
			&lt;h1>Email sent. Please check your inbox.&lt;/h1>
		&lt;/xsl:if>

		&lt;label>Username: &lt;input name="front-end-authentication[username]" type="text"/>&lt;/label>
		&lt;label>Password: &lt;input name="front-end-authentication[password]" type="password"/>&lt;/label>
		&lt;input type="submit" name="action[front-end-authentication][login]" value="Login"/>
		&lt;p>Forgot your password? &lt;a href="{$root}/login/forgot/">Forgot your password?&lt;/a>&lt;/p>
	&lt;/form>
	</code>
</pre>

- Go to system preferences and be sure to select the username and password fields appropriately
- On the system preferences page, choose this page as your login page from the drop box
- Any page with the same type as the one you specified in the system preferences will auto-magically display the 
login page contents instead
- To log out, add `?front-end-authentication-logout=true` to any URL



### TIPS

- If you wish for your users to be able to retrieve passwords, ensure that your usernames are email addresses, 
then create a forgot password page with a form similar to:

<pre>
	<code>
	&lt;form method="POST" action="{$root}/login/">
		&lt;p>Enter your email address below and you will be sent an email containing your password&lt;/p>
  		&lt;label>Email Address: &lt;input name="front-end-authentication[username]" type="text" />&lt;/label>
	  	&lt;input type="submit" name="action[front-end-authentication][forgot]" value="Go" />
	&lt;/form>
	</code>
</pre>

You can customise the email on the preferences page

- It is recommended that you use the "Unique Input" field for the username. This can be found 
at <http://beta.overture21.com/forum/comments.php?DiscussionID=269>
- Setting this extension to use Sessions instead of Cookies (found in the system preferences) will mean the user 
is logged out as soon as the browser is closed.