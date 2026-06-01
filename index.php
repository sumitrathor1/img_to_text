<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image to Text OCR</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #111827;
            padding: 16px;
        }

        .page {
            width: min(920px, 100%);
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #dbe3ee;
            border-radius: 16px;
            padding: 16px;
        }

        h1 {
            margin: 0 0 14px;
            font-size: 1.35rem;
            font-weight: 700;
        }

        .field {
            display: grid;
            gap: 8px;
            margin-bottom: 12px;
        }

        label {
            font-size: 0.95rem;
            font-weight: 600;
        }

        input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cfd8e3;
            border-radius: 10px;
            font-size: 1rem;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }

        button {
            border: 0;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .primary {
            background: #2563eb;
            color: #fff;
        }

        .secondary {
            background: #e5eefb;
            color: #111827;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .box {
            border: 1px solid #dbe3ee;
            border-radius: 12px;
            padding: 12px;
            min-height: 260px;
            background: #fff;
        }

        .box h2 {
            margin: 0 0 10px;
            font-size: 1rem;
        }

        .preview-wrap {
            min-height: 210px;
            display: grid;
            place-items: center;
        }

        #previewImage {
            display: none;
            max-width: 100%;
            max-height: 420px;
            object-fit: contain;
        }

        .placeholder {
            color: #64748b;
            text-align: center;
            font-size: 0.95rem;
        }

        .status {
            margin-top: 10px;
            font-size: 0.92rem;
            color: #64748b;
        }

        .status.error {
            color: #dc2626;
        }

        .status.success {
            color: #15803d;
        }

        .result-box {
            white-space: pre-wrap;
            line-height: 1.6;
            color: #111827;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            min-height: 210px;
        }

        @media (max-width: 700px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <h1>Image to Text OCR</h1>

        <div class="field">
            <label for="imageUrl">Image URL</label>
            <input id="imageUrl" type="url" placeholder="Paste image URL here" autocomplete="off">
        </div>

        <div class="actions">
            <button class="primary" id="extractBtn">Extract Text</button>
            <button class="secondary" id="previewBtn">Preview Image</button>
        </div>

        <div class="grid">
            <section class="box">
                <h2>Preview</h2>
                <div class="preview-wrap">
                    <img id="previewImage" alt="Selected image preview">
                    <div class="placeholder" id="previewPlaceholder">No image loaded yet</div>
                </div>
                <div class="status" id="status">Paste an image URL to start.</div>
            </section>

            <section class="box">
                <h2>Text Output</h2>
                <div class="result-box" id="resultBox">The extracted text will appear here.</div>
            </section>
        </div>
    </main>
</body>
<script>
    const apiKey = "K89155450788957";
    const imageUrlInput = document.getElementById("imageUrl");
    const extractBtn = document.getElementById("extractBtn");
    const previewBtn = document.getElementById("previewBtn");
    const previewImage = document.getElementById("previewImage");
    const previewPlaceholder = document.getElementById("previewPlaceholder");
    const resultBox = document.getElementById("resultBox");
    const status = document.getElementById("status");

    const DEFAULT_TEXT = "The extracted text will appear here.";

    function setStatus(message, kind = "") {
        status.textContent = message;
        status.className = kind ? `status ${kind}` : "status";
    }

    function setPreview(url) {
        if (!url) {
            previewImage.style.display = "none";
            previewPlaceholder.style.display = "block";
            return;
        }

        previewImage.src = url;
        previewImage.style.display = "block";
        previewPlaceholder.style.display = "none";
    }

    function getImageUrl() {
        return imageUrlInput.value.trim();
    }

    async function extractText() {
        const imageUrl = getImageUrl();

        if (!imageUrl) {
            setStatus("Please enter an image URL first.", "error");
            return;
        }

        setStatus("Sending image URL to OCR...", "");
        resultBox.textContent = "Reading the image...";

        try {
            setPreview(imageUrl);

            const ocrUrl = new URL("https://api.ocr.space/parse/imageurl");
            ocrUrl.searchParams.set("apikey", apiKey);
            ocrUrl.searchParams.set("url", imageUrl);
            ocrUrl.searchParams.set("language", "eng");
            ocrUrl.searchParams.set("isOverlayRequired", "false");
            ocrUrl.searchParams.set("filetype", "JPG");
            ocrUrl.searchParams.set("OCREngine", "2");
            ocrUrl.searchParams.set("scale", "true");

            setStatus("Running OCR...", "");
            const ocrResponse = await fetch(ocrUrl.toString(), {
                method: "GET"
            });

            const result = await ocrResponse.json();

            if (result.ParsedResults && result.ParsedResults.length > 0) {
                const extractedText = result.ParsedResults[0].ParsedText.trim();
                resultBox.textContent = extractedText || "No text found in the image.";
                setStatus("Text extracted successfully.", "success");
            } else {
                const errorMessage = Array.isArray(result.ErrorMessage)
                    ? result.ErrorMessage.join(" ")
                    : result.ErrorMessage || "No text found in the image.";
                resultBox.textContent = errorMessage;
                setStatus(errorMessage, "error");
            }
        } catch (error) {
            resultBox.textContent = DEFAULT_TEXT;
            const message = error?.message || "Unknown OCR error";
            setStatus(`OCR error: ${message}`, "error");
        }
    }

    function previewCurrentImage() {
        const imageUrl = getImageUrl();

        if (!imageUrl) {
            setStatus("Please enter an image URL first.", "error");
            return;
        }

        setPreview(imageUrl);
        resultBox.textContent = DEFAULT_TEXT;
        setStatus("Preview updated.", "success");
    }

    extractBtn.addEventListener("click", extractText);
    previewBtn.addEventListener("click", previewCurrentImage);

    imageUrlInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            extractText();
        }
    });
</script>
</html>