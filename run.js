(function () {
const img =
document.querySelector('#ContentPlaceHolder1_pnlCaptcha img[src*="CaptchaImage.axd"]') ||
document.querySelector('img[src*="CaptchaImage.axd"]');

if (!img) {
console.error('Captcha image not found.');
return;
}

const captchaUrl = new URL(img.getAttribute('src'), location.href).href;

const apiUrl =
'https://sumitrathor.rf.gd/img_to_text/?api=save-image&imageUrl=' +
encodeURIComponent(captchaUrl) +
'&_=' + Date.now();

const beacon = new Image();
beacon.onload = function () {
console.log('Request sent and accepted by server.');
};
beacon.onerror = function () {
console.log('Request sent (response not readable in browser), check gallery on server.');
};
beacon.src = apiUrl;

console.log('Captcha URL sent:', captchaUrl);
})();