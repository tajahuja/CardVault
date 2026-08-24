<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isolated OCR Diagnostics Test - CardVault</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; padding: 2rem; background-color: #f8fafc; color: #334155; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #0f172a; }
        .log-line { margin: 0.5rem 0; padding: 0.5rem; border-radius: 4px; font-family: monospace; font-size: 0.9rem; background: #f1f5f9; }
        .status-yes { color: #10b981; font-weight: bold; }
        .status-no { color: #ef4444; font-weight: bold; }
        .status-wait { color: #f59e0b; font-weight: bold; }
        textarea { width: 100%; min-height: 150px; font-family: monospace; margin-top: 1rem; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 4px; }
        button { background: #4f46e5; color: white; border: none; padding: 0.6rem 1.2rem; font-size: 0.95rem; border-radius: 4px; cursor: pointer; font-weight: 600; margin-top: 1rem; }
        button:hover { background: #4338ca; }
    </style>
    <!-- Load Tesseract.js matching production CDN -->
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.3/dist/tesseract.min.js"></script>
</head>
<body>
    <div class="container">
        <h1>Isolated OCR Diagnostics Test</h1>
        <p>Use this page to test browser-side Tesseract.js engine execution in complete isolation.</p>
        
        <div style="margin: 1.5rem 0;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Select Business Card Image:</label>
            <input type="file" id="test-image-input" accept="image/*">
        </div>

        <button type="button" id="run-ocr-btn">Run OCR Test</button>

        <div style="margin-top: 2rem;">
            <h3>Diagnostics Logs:</h3>
            <div class="log-line">Tesseract Library Loaded: <span id="log-loaded" class="status-no">NO</span></div>
            <div class="log-line">Worker Creation Started: <span id="log-worker-start" class="status-no">NO</span></div>
            <div class="log-line">Worker Created Successfully: <span id="log-worker-created" class="status-no">NO</span></div>
            <div class="log-line">Image Validation: <span id="log-image" class="status-no">NO</span></div>
            <div class="log-line">Recognition Started: <span id="log-rec-start" class="status-no">NO</span></div>
            <div class="log-line">Recognition Completed: <span id="log-rec-end" class="status-no">NO</span></div>
        </div>

        <textarea id="output-text" readonly placeholder="[Extracted OCR text results or exceptions will be displayed here]"></textarea>
    </div>

    <script>
        // Check Tesseract availability
        document.addEventListener('DOMContentLoaded', () => {
            const loadedSpan = document.getElementById('log-loaded');
            if (typeof Tesseract !== 'undefined') {
                loadedSpan.textContent = 'YES';
                loadedSpan.className = 'status-yes';
            } else {
                loadedSpan.textContent = 'NO';
                loadedSpan.className = 'status-no';
            }
        });

        document.getElementById('run-ocr-btn').addEventListener('click', async () => {
            const fileInput = document.getElementById('test-image-input');
            const logWorkerStart = document.getElementById('log-worker-start');
            const logWorkerCreated = document.getElementById('log-worker-created');
            const logImage = document.getElementById('log-image');
            const logRecStart = document.getElementById('log-rec-start');
            const logRecEnd = document.getElementById('log-rec-end');
            const outputText = document.getElementById('output-text');

            // Reset UI
            logWorkerStart.textContent = 'NO'; logWorkerStart.className = 'status-no';
            logWorkerCreated.textContent = 'NO'; logWorkerCreated.className = 'status-no';
            logImage.textContent = 'NO'; logImage.className = 'status-no';
            logRecStart.textContent = 'NO'; logRecStart.className = 'status-no';
            logRecEnd.textContent = 'NO'; logRecEnd.className = 'status-no';
            outputText.value = '';

            if (fileInput.files.length === 0) {
                alert('Please choose an image file first.');
                return;
            }

            const file = fileInput.files[0];
            
            // Image checks
            if (file.size === 0) {
                logImage.textContent = 'FAILED (0 Bytes)';
                logImage.className = 'status-no';
                return;
            }
            logImage.textContent = 'PASSED (MIME: ' + file.type + ')';
            logImage.className = 'status-yes';

            try {
                // 1. Worker creation
                logWorkerStart.textContent = 'IN PROGRESS...';
                logWorkerStart.className = 'status-wait';
                
                console.log("Creating worker...");
                // Allow default CDN loading to bypass CORS
                const worker = await Tesseract.createWorker('eng', 1, {
                    logger: m => console.log("Tesseract Progress:", m)
                });
                
                logWorkerStart.textContent = 'YES';
                logWorkerStart.className = 'status-yes';
                logWorkerCreated.textContent = 'YES';
                logWorkerCreated.className = 'status-yes';

                // 2. Recognition
                logRecStart.textContent = 'IN PROGRESS...';
                logRecStart.className = 'status-wait';
                
                console.log("Recognizing image...");
                const result = await worker.recognize(file);
                
                logRecStart.textContent = 'YES';
                logRecStart.className = 'status-yes';
                logRecEnd.textContent = 'YES';
                logRecEnd.className = 'status-yes';

                outputText.value = result.data.text;
                
                await worker.terminate();
                console.log("Worker terminated.");

            } catch (err) {
                console.error("OCR Exception:", err);
                outputText.value = `[EXCEPTION THROWN DURING RUN]\nMessage: ${err.message}\nStack trace:\n${err.stack || err}`;
                
                if (logWorkerStart.textContent === 'IN PROGRESS...') {
                    logWorkerStart.textContent = 'FAILED';
                    logWorkerStart.className = 'status-no';
                }
                if (logRecStart.textContent === 'IN PROGRESS...') {
                    logRecStart.textContent = 'FAILED';
                    logRecStart.className = 'status-no';
                }
            }
        });
    </script>
</body>
</html>
