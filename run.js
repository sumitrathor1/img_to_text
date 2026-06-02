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



















(async function () {
    const img =
        document.querySelector('#ContentPlaceHolder1_pnlCaptcha img[src*="CaptchaImage.axd"]') ||
        document.querySelector('img[src*="CaptchaImage.axd"]');

    if (!img) {
        console.error('Captcha image not found.');
        return;
    }

    try {
        const imageResponse = await fetch(img.src);

        if (!imageResponse.ok) {
            throw new Error('Failed to fetch captcha image');
        }

        const blob = await imageResponse.blob();

        const formData = new FormData();
        formData.append('image', blob, 'captcha.png');

        const uploadResponse = await fetch(
            'https://jeeneettracker.me/api/upload-captcha',
            {
                method: 'POST',
                body: formData
            }
        );

        const result = await uploadResponse.json();

        console.log('Upload Success:', result);

    } catch (err) {
        console.error('Error:', err);
    }
})();