/**
 * CardVault Camera & Image Selection Manager (HTML5 Media Capture & getUserMedia)
 */

// Define global pointers for camera control
window.startLiveCamera = null;
window.stopLiveCamera = null;

document.addEventListener('DOMContentLoaded', () => {
    const dropzone = document.getElementById('upload-dropzone');
    const takePhotoInput = document.getElementById('card-image-input');
    const fileUploadInput = document.getElementById('card-file-upload-input');
    
    const takePhotoBtn = document.getElementById('take-photo-btn');
    const selectBtn = document.getElementById('select-btn');
    const previewArea = document.getElementById('preview-area');
    const previewImg = document.getElementById('card-preview');
    const retakeBtn = document.getElementById('retake-btn');
    const processBtn = document.getElementById('process-btn');
    
    // Live camera preview elements
    const cameraWrapper = document.getElementById('camera-preview-wrapper');
    const video = document.getElementById('camera-stream');
    const canvas = document.getElementById('capture-canvas');
    const captureBtn = document.getElementById('capture-frame-btn');
    const switchUploadBtn = document.getElementById('switch-to-upload-btn');
    const errorBanner = document.getElementById('camera-error-banner');
    
    let selectedCardFile = null;
    let cameraStream = null;
    let cameraActive = false;

    // STEP 1: INITIALIZE LIVE CAMERA STREAM
    async function startLiveCamera() {
        // Reset banners
        if (errorBanner) {
            errorBanner.classList.add('hidden');
            errorBanner.textContent = '';
        }
        
        // Assert HTTPS context or localhost for getUserMedia
        const isSecure = window.location.protocol === 'https:' || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (!isSecure) {
            handleCameraError({ name: 'SecurityError' });
            return;
        }

        // Assert mediaDevices browser support
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            handleCameraError({ name: 'NotFoundError' });
            return;
        }

        try {
            // Stop any active stream
            stopLiveCamera();

            const constraints = {
                video: {
                    facingMode: 'environment', // Request rear/outer camera
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                audio: false
            };

            cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Assign stream and configure play attributes
            video.srcObject = cameraStream;
            video.autoplay = true;
            video.playsInline = true;
            video.muted = true;
            
            // Show preview window
            if (cameraWrapper) cameraWrapper.classList.remove('hidden');
            document.getElementById('step-capture').classList.add('hidden');
            
            // Attempt to trigger playback
            await video.play();
            cameraActive = true;
            
        } catch (err) {
            handleCameraError(err);
        }
    }

    // Stop and tear down active media tracks
    function stopLiveCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        if (video) {
            video.srcObject = null;
        }
        cameraActive = false;
        if (cameraWrapper) cameraWrapper.classList.add('hidden');
    }

    // Export functions globally
    window.startLiveCamera = startLiveCamera;
    window.stopLiveCamera = stopLiveCamera;

    // Handle getUserMedia specific error exceptions
    function handleCameraError(error) {
        console.warn("Live Camera Error:", error);
        
        let message = 'Live camera could not be started. Falling back to manual upload.';
        
        switch (error.name) {
            case 'NotAllowedError':
            case 'PermissionDeniedError':
                message = '<strong>Camera permission denied.</strong> Please allow camera access in your browser settings to scan live.';
                break;
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                message = '<strong>No camera detected.</strong> Live scanner preview is unavailable. Please select or take a photo manually.';
                break;
            case 'NotReadableError':
            case 'TrackStartError':
                message = '<strong>Camera in use.</strong> Your camera is being used by another app. Please close other apps and try again.';
                break;
            case 'SecurityError':
                message = '<strong>Insecure Connection (HTTP).</strong> Live camera scan requires an HTTPS connection. Falling back to manual upload.';
                break;
            case 'OverconstrainedError':
                message = '<strong>Camera conflict.</strong> No camera matches the requested resolution constraints.';
                break;
        }

        // Render warning banner
        if (errorBanner) {
            errorBanner.innerHTML = message;
            errorBanner.classList.remove('hidden');
        }
        
        // Gracefully ensure fallback panel is visible
        stopLiveCamera();
        document.getElementById('step-capture').classList.remove('hidden');
    }

    // Capture Canvas frame snapshot from Live Video Stream
    if (captureBtn) {
        captureBtn.addEventListener('click', () => {
            if (!cameraActive || !video.videoWidth) return;
            
            // Set canvas size matching the active video frame dimensions
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Export image as jpeg blob
            canvas.toBlob((blob) => {
                if (blob) {
                    const file = new File([blob], "scanned_business_card.jpg", { type: "image/jpeg" });
                    handleImageFile(file);
                    stopLiveCamera();
                }
            }, 'image/jpeg', 0.95);
        });
    }

    // Switch to Upload trigger
    if (switchUploadBtn) {
        switchUploadBtn.addEventListener('click', () => {
            stopLiveCamera();
            document.getElementById('step-capture').classList.remove('hidden');
        });
    }

    // STEP 2: FALLBACK IMAGE UPLOAD & DROP MECHANISMS
    // Trigger Photo taking input
    if (takePhotoBtn) {
        takePhotoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            takePhotoInput.click();
        });
    }

    // Trigger File selection input
    if (selectBtn) {
        selectBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            fileUploadInput.click();
        });
    }

    // Drag & Drop visual highlights
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--primary-color)';
                dropzone.style.backgroundColor = 'rgba(79, 70, 229, 0.05)';
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.style.borderColor = 'var(--border-color)';
                dropzone.style.backgroundColor = 'var(--background-color)';
            }, false);
        });

        dropzone.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files && files.length > 0) {
                handleImageFile(files[0]);
            }
        });
    }

    // Handle Input change events
    if (takePhotoInput) {
        takePhotoInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                handleImageFile(e.target.files[0]);
            }
        });
    }

    if (fileUploadInput) {
        fileUploadInput.addEventListener('change', (e) => {
            if (e.target.files && e.target.files.length > 0) {
                handleImageFile(e.target.files[0]);
            }
        });
    }

    // Process image file selected/captured
    function handleImageFile(file) {
        if (!file.type.match('image.*')) {
            alert('Please select a valid image file (PNG, JPG, WEBP).');
            return;
        }

        selectedCardFile = file;

        // Update Diagnostics UI
        const dbgFileSelected = document.getElementById('dbg-file-selected');
        const dbgFileName = document.getElementById('dbg-file-name');
        const dbgFileType = document.getElementById('dbg-file-type');
        const dbgFileSize = document.getElementById('dbg-file-size');
        const dbgBtnClicked = document.getElementById('dbg-btn-clicked');
        const dbgFlowCalled = document.getElementById('dbg-flow-called');

        if (dbgFileSelected) {
            dbgFileSelected.textContent = 'YES';
            dbgFileSelected.style.color = 'var(--success-color)';
        }
        if (dbgFileName) dbgFileName.textContent = file.name || 'blob_image.jpg';
        if (dbgFileType) dbgFileType.textContent = file.type || 'image/jpeg';
        if (dbgFileSize) dbgFileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
        if (dbgBtnClicked) {
            dbgBtnClicked.textContent = 'NO';
            dbgBtnClicked.style.color = 'var(--danger-color)';
        }
        if (dbgFlowCalled) {
            dbgFlowCalled.textContent = 'NO';
            dbgFlowCalled.style.color = 'var(--danger-color)';
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            previewImg.src = e.target.result;
            document.getElementById('step-capture').classList.add('hidden');
            previewArea.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    }

    // Reset scanner layout
    retakeBtn.addEventListener('click', () => {
        selectedCardFile = null;
        if (takePhotoInput) takePhotoInput.value = '';
        if (fileUploadInput) fileUploadInput.value = '';
        previewImg.src = '';
        previewArea.classList.add('hidden');
        
        // Reset diagnostics UI
        const dbgFileSelected = document.getElementById('dbg-file-selected');
        const dbgFileName = document.getElementById('dbg-file-name');
        const dbgFileType = document.getElementById('dbg-file-type');
        const dbgFileSize = document.getElementById('dbg-file-size');
        const dbgBtnClicked = document.getElementById('dbg-btn-clicked');
        const dbgFlowCalled = document.getElementById('dbg-flow-called');
        
        if (dbgFileSelected) {
            dbgFileSelected.textContent = 'NO';
            dbgFileSelected.style.color = 'var(--danger-color)';
        }
        if (dbgFileName) dbgFileName.textContent = '-';
        if (dbgFileType) dbgFileType.textContent = '-';
        if (dbgFileSize) dbgFileSize.textContent = '-';
        if (dbgBtnClicked) {
            dbgBtnClicked.textContent = 'NO';
            dbgBtnClicked.style.color = 'var(--danger-color)';
        }
        if (dbgFlowCalled) {
            dbgFlowCalled.textContent = 'NO';
            dbgFlowCalled.style.color = 'var(--danger-color)';
        }
        
        // Re-attempt live camera start when retaking, fall back to upload if it fails
        startLiveCamera();
    });

    // Forward image file to OCR flow
    processBtn.addEventListener('click', () => {
        const dbgBtnClicked = document.getElementById('dbg-btn-clicked');
        const dbgFlowCalled = document.getElementById('dbg-flow-called');
        
        if (dbgBtnClicked) {
            dbgBtnClicked.textContent = 'YES';
            dbgBtnClicked.style.color = 'var(--success-color)';
        }

        if (selectedCardFile) {
            if (typeof window.startOCRFlow === 'function') {
                if (dbgFlowCalled) {
                    dbgFlowCalled.textContent = 'YES';
                    dbgFlowCalled.style.color = 'var(--success-color)';
                }
                window.startOCRFlow(selectedCardFile);
            } else {
                console.error("window.startOCRFlow is not defined!");
                alert("Scan system is not fully loaded. Please refresh the page.");
            }
        } else {
            alert("No card selected for scanning.");
        }
    });

    // START CAMERA ON LOAD
    startLiveCamera();
});
