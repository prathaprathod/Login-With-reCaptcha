function lwr() {
	var recaptcha = document.getElementsByClassName("lwr-recaptcha");
	for (var i = 0; i < recaptcha.length; i++) {
		grecaptcha.render(recaptcha.item(i), {"sitekey" : lwr_recaptcha.site_key});
	}
};