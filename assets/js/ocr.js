function setStepStatus(stepNum, status) {
    const el = document.getElementById('step-ind-' + stepNum);
    if (!el) return;
    const dot = el.querySelector('.step-dot');
    if (status === 'active') {
        el.style.color = 'var(--primary-color)';
        dot.style.borderColor = 'var(--primary-color)';
        dot.style.color = 'var(--primary-color)';
        dot.style.backgroundColor = 'transparent';
        dot.innerHTML = stepNum;
    } else if (status === 'completed') {
        el.style.color = 'var(--secondary-color)';
        dot.style.borderColor = 'var(--primary-color)';
        dot.style.color = '#fff';
        dot.style.backgroundColor = 'var(--primary-color)';
        dot.innerHTML = '✓';
    } else {
        el.style.color = 'var(--text-muted)';
        dot.style.borderColor = 'var(--border-color)';
        dot.style.color = 'var(--text-color)';
        dot.style.backgroundColor = 'transparent';
        dot.innerHTML = stepNum;
    }
}
window.setStepStatus = setStepStatus;

function updateEventSource(selectEl) {
    const leadSrc = document.getElementById('lead_source');
    if (selectEl && selectEl.value !== '0' && leadSrc) {
        leadSrc.value = 'Conference';
    }
}
window.updateEventSource = updateEventSource;

document.addEventListener('DOMContentLoaded', () => {
    // Audit Tesseract library availability on load
    const dbgTess = document.getElementById('dbg-tesseract');
    if (dbgTess) {
        if (typeof Tesseract !== 'undefined') {
            dbgTess.textContent = 'YES';
            dbgTess.style.color = 'var(--success-color)';
        } else {
            dbgTess.textContent = 'NO (Load Error)';
            dbgTess.style.color = 'var(--danger-color)';
        }
    }
});

window.startOCRFlow = function(imageFile) {
    const stepCapture = document.getElementById('step-capture');
    const stepProcessing = document.getElementById('step-processing');
    const stepReview = document.getElementById('step-review');
    const statusTitle = document.getElementById('ocr-status-title');
    const progressBar = document.getElementById('ocr-progress-bar');
    const progressText = document.getElementById('ocr-progress-text');
    
    // Debug diagnostics DOM endpoints
    const dbgWorker = document.getElementById('dbg-worker');
    const dbgLang = document.getElementById('dbg-lang');
    const dbgImage = document.getElementById('dbg-image');
    const dbgStarted = document.getElementById('dbg-started');
    const dbgCompleted = document.getElementById('dbg-completed');
    const dbgChars = document.getElementById('dbg-chars');
    const dbgRawText = document.getElementById('dbg-raw-text');

    // Reset Debug Diagnostics UI
    const dbgFlowCalled = document.getElementById('dbg-flow-called');
    if (dbgFlowCalled) {
        dbgFlowCalled.textContent = 'YES';
        dbgFlowCalled.style.color = 'var(--success-color)';
    }
    if (dbgWorker) { dbgWorker.textContent = 'NO'; dbgWorker.style.color = 'var(--danger-color)'; }
    if (dbgLang) { dbgLang.textContent = 'NO'; dbgLang.style.color = 'var(--danger-color)'; }
    if (dbgImage) { dbgImage.textContent = 'NO'; dbgImage.style.color = 'var(--danger-color)'; }
    if (dbgStarted) { dbgStarted.textContent = 'NO'; dbgStarted.style.color = 'var(--danger-color)'; }
    if (dbgCompleted) { dbgCompleted.textContent = 'NO'; dbgCompleted.style.color = 'var(--danger-color)'; }
    if (dbgChars) { dbgChars.textContent = '0'; dbgChars.style.color = 'inherit'; }
    if (dbgRawText) dbgRawText.value = '';

    // Switch to processing layout
    stepCapture.classList.add('hidden');
    stepProcessing.classList.remove('hidden');
    
    // Set initial step indicators
    setStepStatus(1, 'completed');
    setStepStatus(2, 'active');
    setStepStatus(3, 'pending');
    setStepStatus(4, 'pending');
    
    updateProgress('Initializing OCR engine...', 10);
    
    // STEP 1: PRE-VALIDATE IMAGE INTEGRITY
    function validateImage() {
        return new Promise((resolve, reject) => {
            if (!imageFile || !(imageFile instanceof Blob || imageFile instanceof File)) {
                return reject(new Error("Selected target is not a valid file object."));
            }
            if (imageFile.size <= 0) {
                return reject(new Error("Selected file is empty (0 bytes)."));
            }

            // Create temporary image element to check decode rendering
            const img = new Image();
            img.onload = () => {
                if (img.width <= 0 || img.height <= 0) {
                    reject(new Error("Invalid image dimension boundaries."));
                } else {
                    if (dbgImage) {
                        dbgImage.textContent = `YES (${img.width}x${img.height})`;
                        dbgImage.style.color = 'var(--success-color)';
                    }
                    resolve();
                }
                URL.revokeObjectURL(img.src);
            };
            img.onerror = () => {
                reject(new Error("Image decode failed. The file is corrupt or unreadable."));
                URL.revokeObjectURL(img.src);
            };
            img.src = URL.createObjectURL(imageFile);
        });
    }

    // STEP 2: RUN BROWSER-SIDE OCR (Tesseract.js)
    async function performOCR() {
        try {
            if (dbgWorker) { dbgWorker.textContent = 'IN PROGRESS'; dbgWorker.style.color = 'var(--warning-color)'; }
            
            // Build options object
            const options = {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        const pct = Math.round(m.progress * 100);
                        updateProgress('Reading business card text...', pct);
                    }
                }
            };
            
            // Safe cross-origin handling: Only specify paths if they are local paths
            // If they are CDN URLs, omit them to let Tesseract.js use its secure cross-origin worker Blob loader.
            if (window.OCR_CONFIG) {
                const isLocal = (path) => path && !path.startsWith('http://') && !path.startsWith('https://') && !path.startsWith('//');
                
                if (isLocal(window.OCR_CONFIG.workerPath)) {
                    options.workerPath = window.OCR_CONFIG.workerPath;
                }
                if (isLocal(window.OCR_CONFIG.corePath)) {
                    options.corePath = window.OCR_CONFIG.corePath;
                }
                if (isLocal(window.OCR_CONFIG.langPath)) {
                    options.langPath = window.OCR_CONFIG.langPath;
                }
            }
            
            console.log("Initializing worker with options:", options);
            
            const worker = await Tesseract.createWorker('eng', 1, options);
            
            if (dbgWorker) { dbgWorker.textContent = 'YES'; dbgWorker.style.color = 'var(--success-color)'; }
            if (dbgLang) { dbgLang.textContent = 'YES'; dbgLang.style.color = 'var(--success-color)'; }
            if (dbgStarted) { dbgStarted.textContent = 'YES'; dbgStarted.style.color = 'var(--success-color)'; }
            
            const response = await worker.recognize(imageFile);
            await worker.terminate();
            
            const extractedText = response.data.text;
            
            setStepStatus(2, 'completed');
            setStepStatus(3, 'active');
            
            // Write output to diagnostics console
            if (dbgCompleted) { dbgCompleted.textContent = 'YES'; dbgCompleted.style.color = 'var(--success-color)'; }
            if (dbgChars) dbgChars.textContent = extractedText.length;
            if (dbgRawText) dbgRawText.value = extractedText;
            
            return extractedText;
        } catch (err) {
            console.error("Tesseract Error:", err);
            throw err; // bubble up to general catch for diagnostics display
        }
    }
    
    // STEP 3: SECURE FILE UPLOAD HANDLER
    function uploadCardImage() {
        updateProgress('Uploading image securely...', 80);
        
        const csrfToken = document.querySelector('input[name="csrf_token"]').value;
        const formData = new FormData();
        formData.append('card_image', imageFile);
        formData.append('csrf_token', csrfToken);
        
        return fetch('api/upload_card.php', {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            }
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.message || 'File upload failed.');
                }
                return data;
            });
        })
        .then(data => {
            if (data.success && data.filename) {
                setStepStatus(4, 'completed');
                return data.filename;
            } else {
                throw new Error(data.message || 'Upload failed.');
            }
        });
    }
    
    // Helper to update progress display
    function updateProgress(status, percentage) {
        statusTitle.textContent = status;
        progressBar.style.width = percentage + '%';
        progressText.textContent = percentage + '%';
    }
    
    // Execution pipeline: Validate → OCR + Upload
    validateImage()
    .then(() => {
        return Promise.all([
            performOCR(),
            uploadCardImage()
        ]);
    })
    .then(([ocrText, filename]) => {
        updateProgress('Parsing information...', 100);
        
        // Save uploaded filename in form
        document.getElementById('card_image_filename').value = filename;
        
        // Save raw OCR output in database field
        document.getElementById('ocr_raw_text').value = ocrText;
        
        // Use heuristics parser to populate review form
        if (typeof window.parseOCRText === 'function') {
            const structuredData = window.parseOCRText(ocrText);
            populateReviewForm(structuredData);
        }
        
        // Update parsing step status
        setStepStatus(3, 'completed');
        
        // Set preview image to private gateway with temp_name authorization
        document.getElementById('card-review-image').src = `api/view_card.php?temp_name=${filename}`;
        
        // Switch to review form screen
        stepProcessing.classList.add('hidden');
        stepReview.classList.remove('hidden');
    })
    .catch(error => {
        // Reset step statuses on failure
        setStepStatus(1, 'reset');
        setStepStatus(2, 'reset');
        setStepStatus(3, 'reset');
        setStepStatus(4, 'reset');
        // Display the actual Javascript error in the debug panel!
        if (dbgRawText) {
            dbgRawText.value = `[DEBUG EXCEPTION CALLED]\nError Message: ${error.message}\nError Details:\n${error.stack || error}`;
        }
        if (dbgWorker && dbgWorker.textContent === 'IN PROGRESS') {
            dbgWorker.textContent = 'FAILED';
            dbgWorker.style.color = 'var(--danger-color)';
        }
        
        alert(error.message || 'An error occurred during scanning. Please try again.');
        console.error(error);
        
        // Recover layout state
        stepProcessing.classList.add('hidden');
        stepCapture.classList.remove('hidden');
    });
};

// Form populate helper
function populateReviewForm(data) {
    document.getElementById('full_name').value = data.fullName || '';
    document.getElementById('first_name').value = data.firstName || '';
    document.getElementById('last_name').value = data.lastName || '';
    document.getElementById('job_title').value = data.jobTitle || '';
    document.getElementById('company').value = data.company || '';
    document.getElementById('phone').value = data.phone || '';
    document.getElementById('alternate_phone').value = data.alternatePhone || '';
    document.getElementById('email').value = data.email || '';
    document.getElementById('alternate_email').value = data.alternateEmail || '';
    document.getElementById('website').value = data.website || '';
    document.getElementById('linkedin_url').value = data.linkedinUrl || '';
    document.getElementById('address').value = data.address || '';
    document.getElementById('city').value = data.city || '';
    document.getElementById('state').value = data.state || '';
    document.getElementById('country').value = data.country || '';
    document.getElementById('postal_code').value = data.postalCode || '';
}

// Wire Review Cancel and Start Over actions
document.addEventListener('DOMContentLoaded', () => {
    const startOverBtn = document.getElementById('start-over-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const stepCapture = document.getElementById('step-capture');
    const stepReview = document.getElementById('step-review');
    const fileInput = document.getElementById('card-image-input');
    const fileUploadInput = document.getElementById('card-file-upload-input');
    const previewImg = document.getElementById('card-preview');
    const previewArea = document.getElementById('preview-area');
    const dropzone = document.getElementById('upload-dropzone');
    
    function resetScanner() {
        fileInput.value = '';
        fileUploadInput.value = '';
        previewImg.src = '';
        previewArea.classList.add('hidden');
        dropzone.classList.remove('hidden');
        
        // Reset step statuses
        setStepStatus(1, 'reset');
        setStepStatus(2, 'reset');
        setStepStatus(3, 'reset');
        setStepStatus(4, 'reset');
        
        stepReview.classList.add('hidden');
        stepCapture.classList.remove('hidden');
        
        // Reset review form fields
        const saveForm = document.getElementById('save-contact-form');
        if (saveForm) {
            saveForm.reset();
            // Explicitly clear hidden fields
            const cardFilename = document.getElementById('card_image_filename');
            const ocrRawVal = document.getElementById('ocr_raw_text');
            const ignoreDup = document.getElementById('ignore_duplicate');
            if (cardFilename) cardFilename.value = '';
            if (ocrRawVal) ocrRawVal.value = '';
            if (ignoreDup) ignoreDup.value = '0';
        }
        
        // Refresh diagnostics labels
        const dbgWorker = document.getElementById('dbg-worker');
        const dbgLang = document.getElementById('dbg-lang');
        const dbgImage = document.getElementById('dbg-image');
        const dbgStarted = document.getElementById('dbg-started');
        const dbgCompleted = document.getElementById('dbg-completed');
        const dbgChars = document.getElementById('dbg-chars');
        const dbgRawText = document.getElementById('dbg-raw-text');
        
        if (dbgWorker) dbgWorker.textContent = 'NO';
        if (dbgLang) dbgLang.textContent = 'NO';
        if (dbgImage) dbgImage.textContent = 'NO';
        if (dbgStarted) dbgStarted.textContent = 'NO';
        if (dbgCompleted) dbgCompleted.textContent = 'NO';
        if (dbgChars) dbgChars.textContent = '0';
        if (dbgRawText) dbgRawText.value = '';
        
        // Reload camera automatically on reset
        if (typeof window.startLiveCamera === 'function') {
            window.startLiveCamera();
        }
    }
    
    const startOverBtnReview = document.getElementById('start-over-btn-review');
    
    if (startOverBtn) startOverBtn.addEventListener('click', resetScanner);
    if (startOverBtnReview) startOverBtnReview.addEventListener('click', resetScanner);
    if (cancelBtn) cancelBtn.addEventListener('click', () => {
        if (confirm('Cancel scanning? Any changes will be lost.')) {
            window.location.href = 'dashboard.php';
        }
    });

    // Handle Review Save Submission
    const saveForm = document.getElementById('save-contact-form');
    if (saveForm) {
        saveForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitReviewSave();
        });
    }
    
    let existingIdForUpdate = null;
    
    function submitReviewSave() {
        const form = document.getElementById('save-contact-form');
        const saveBtn = document.getElementById('save-btn');
        const originalText = saveBtn.textContent;
        
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
        
        const formData = new FormData(form);
        
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': formData.get('csrf_token')
            }
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.message || 'An error occurred while saving the contact.');
                }
                return data;
            });
        })
        .then(data => {
            if (data.duplicate) {
                // Duplicate detected: display modal alert
                document.getElementById('duplicate-warning-text').textContent = data.reason;
                existingIdForUpdate = data.existing_id;
                document.getElementById('duplicate-modal').classList.remove('hidden');
                
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            } else if (data.success && data.redirect) {
                alert('Contact saved successfully.');
                window.location.href = data.redirect;
            } else {
                throw new Error('Unexpected response format.');
            }
        })
        .catch(error => {
            alert(error.message);
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        });
    }

    // Modal Interaction inside Scanner
    const dupCancelBtn = document.getElementById('dup-cancel-btn');
    const dupCreateBtn = document.getElementById('dup-create-btn');
    const dupUpdateBtn = document.getElementById('dup-update-btn');
    
    if (dupCancelBtn) {
        dupCancelBtn.addEventListener('click', function() {
            document.getElementById('duplicate-modal').classList.add('hidden');
        });
    }

    if (dupCreateBtn) {
        dupCreateBtn.addEventListener('click', function() {
            document.getElementById('ignore_duplicate').value = "1";
            document.getElementById('duplicate-modal').classList.add('hidden');
            submitReviewSave();
        });
    }

    if (dupUpdateBtn) {
        dupUpdateBtn.addEventListener('click', function() {
            if (existingIdForUpdate) {
                window.location.href = 'contact.php?id=' + existingIdForUpdate;
            }
        });
    }
});
