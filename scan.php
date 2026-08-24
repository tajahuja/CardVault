<?php
/**
 * Unified Card Scanner & OCR Page
 */

$pageTitle = 'Scan Card';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/csrf.php';
?>

<div class="page-header">
    <div class="page-title">
        <h1>Scan Business Card</h1>
        <p>Photograph or upload a business card to automatically import it</p>
    </div>
    <div class="header-actions">
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </div>
</div>

<div class="scanner-container">
    <!-- Alert Banner for Camera Errors -->
    <div id="camera-error-banner" class="alert alert-warning hidden" style="margin-bottom: 1.5rem; text-align: left;"></div>

    <!-- Live Camera Preview Element (Attempted first) -->
    <div id="camera-preview-wrapper" class="card hidden" style="margin-bottom: 1.5rem; overflow: hidden;">
        <div class="card-header"><h3 class="card-title">🎥 Live Business Card Scanner</h3></div>
        <div class="card-body" style="text-align: center; padding: 1rem;">
            <div style="background-color: #000; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--border-color); position: relative; max-width: 640px; margin: 0 auto; box-shadow: var(--shadow-md);">
                <video id="camera-stream" autoplay playsinline muted style="width: 100%; height: auto; display: block; background: #000;"></video>
                <div class="camera-guideline" style="position: absolute; border: 2px dashed rgba(255,255,255,0.6); border-radius: 4px; left: 8%; top: 15%; width: 84%; height: 70%; pointer-events: none; display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
                    <span style="color: rgba(255,255,255,0.8); font-size: 0.8rem; background: rgba(15, 23, 42, 0.7); padding: 0.25rem 0.625rem; border-radius: var(--radius-sm); font-weight: 500;">Align Business Card inside frame</span>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" id="capture-frame-btn">📸 Capture Photo</button>
                <button type="button" class="btn btn-secondary" id="switch-to-upload-btn">📁 Choose from Files</button>
            </div>
        </div>
    </div>

    <!-- Hidden Capture Canvas -->
    <canvas id="capture-canvas" style="display: none;"></canvas>

    <!-- STEP 1: Image Select & Capture Fallback/Upload -->
    <div id="step-capture" class="card">
        <div class="card-body" style="text-align: center; padding: 3rem 1.5rem;">
            <div id="upload-dropzone" class="dropzone-area">
                <div class="dropzone-icon">📇</div>
                <h3>Scan or Upload Business Card</h3>
                <p style="margin-bottom: 1.5rem;">Use your device camera or upload an image file (JPG, PNG, WEBP)</p>
                
                <!-- Double camera inputs for mobile fallback and standard upload -->
                <input type="file" id="card-image-input" accept="image/*" capture="environment" style="display: none;">
                <input type="file" id="card-file-upload-input" accept="image/*" style="display: none;">
                
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <button type="button" class="btn btn-primary" id="take-photo-btn">📸 Take Photo</button>
                    <button type="button" class="btn btn-secondary" id="select-btn">📁 Upload Image</button>
                </div>
            </div>
            
            <div id="preview-area" class="hidden" style="margin-top: 1.5rem;">
                <div class="image-preview-frame">
                    <img id="card-preview" src="" alt="Business Card Preview">
                </div>
                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <button type="button" class="btn btn-secondary" id="retake-btn">🔄 Choose Different</button>
                    <button type="button" class="btn btn-primary" id="process-btn">✨ Read Card (OCR)</button>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: OCR Loading Progress -->
    <div id="step-processing" class="card hidden">
        <div class="card-body" style="text-align: center; padding: 4rem 2rem;">
            <div class="spinner" style="margin: 0 auto 2rem auto; width: 3.5rem; height: 3.5rem; border-width: 4px; border-top-color: var(--primary-color);"></div>
            <h2 id="ocr-status-title" style="margin-bottom: 0.5rem; color: var(--secondary-color);">Preparing OCR Engine...</h2>
            <div class="progress-bar-container">
                <div id="ocr-progress-bar" class="progress-bar-fill" style="width: 0%;"></div>
            </div>
            <p id="ocr-progress-text" style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">0%</p>
        </div>
    </div>

    <!-- STEP 3: Review / Edit Form (Single screen workflow) -->
    <div id="step-review" class="hidden">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; align-items: start;">
            <!-- Left Side: Card Image for easy reference -->
            <div class="card" style="position: sticky; top: 1.5rem;">
                <div class="card-header"><h3 class="card-title">Scanned Business Card</h3></div>
                <div class="card-body" style="padding: 0.75rem; text-align: center;">
                    <div style="background-color: #f1f5f9; border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border-color);">
                        <img id="card-review-image" src="" alt="Uploaded Card" style="width: 100%; height: auto; display: block; object-fit: contain;">
                    </div>
                    <div style="margin-top: 1rem;">
                        <button type="button" class="btn btn-secondary btn-block" id="start-over-btn">📷 Scan Another Card</button>
                    </div>
                </div>
            </div>

            <!-- Right Side: Editable structured info form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Review Contact Information</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">Please verify the information before saving.</div>
                    
                    <form id="save-contact-form" action="api/save_contact.php" method="POST">
                        <?php csrf_field(); ?>
                        <input type="hidden" id="card_image_filename" name="original_card_image" value="">
                        <input type="hidden" name="source" value="Business Card">
                        <input type="hidden" id="ocr_raw_text" name="ocr_raw_text" value="">
                        <input type="hidden" id="ignore_duplicate" name="ignore_duplicate" value="0">

                        <!-- Presentation Metadata -->
                        <div style="margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label for="sr_no">Sr. No.</label>
                                <input type="text" id="sr_no" name="sr_no" value="Auto-assigned on Save" readonly style="background-color: var(--background-color); color: var(--text-muted); cursor: not-allowed; font-weight: 500;">
                            </div>
                        </div>

                        <!-- Personal -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">Personal</h4>
                            <div class="form-group">
                                <label for="full_name">Person Name</label>
                                <input type="text" id="full_name" name="full_name" required>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name">
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name">
                                </div>
                            </div>
                        </div>

                        <!-- Professional -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">Professional</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="company">Organization</label>
                                    <input type="text" id="company" name="company">
                                </div>
                                <div class="form-group">
                                    <label for="job_title">Designation</label>
                                    <input type="text" id="job_title" name="job_title">
                                </div>
                            </div>
                        </div>

                        <!-- Contact -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">Contact</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="phone">Mobile Number</label>
                                    <input type="text" id="phone" name="phone">
                                </div>
                                <div class="form-group">
                                    <label for="alternate_phone">Alternate Phone</label>
                                    <input type="text" id="alternate_phone" name="alternate_phone">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" id="email" name="email">
                                </div>
                                <div class="form-group">
                                    <label for="alternate_email">Alternate Email</label>
                                    <input type="email" id="alternate_email" name="alternate_email">
                                </div>
                            </div>
                        </div>

                        <!-- Online -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">Online</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="website">Website</label>
                                    <input type="url" id="website" name="website">
                                </div>
                                <div class="form-group">
                                    <label for="linkedin_url">LinkedIn</label>
                                    <input type="url" id="linkedin_url" name="linkedin_url">
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">Location</h4>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" style="min-height: 60px;"></textarea>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city">
                                </div>
                                <div class="form-group">
                                    <label for="state">State</label>
                                    <input type="text" id="state" name="state">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <div class="form-group">
                                    <label for="country">Country</label>
                                    <input type="text" id="country" name="country">
                                </div>
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code">
                                </div>
                            </div>
                        </div>

                        <!-- Relationship Context -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">Relationship</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="date_met">Date Met</label>
                                    <input type="date" id="date_met" name="date_met" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="place_met">Place Met</label>
                                    <input type="text" id="place_met" name="place_met" placeholder="Mumbai Travel Expo">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div class="form-group">
                                    <label for="follow_up_date">Follow-up Date</label>
                                    <input type="date" id="follow_up_date" name="follow_up_date">
                                </div>
                                <div class="form-group">
                                    <label for="status">Status</label>
                                    <select id="status" name="status">
                                        <option value="New" selected>New</option>
                                        <option value="Contacted">Contacted</option>
                                        <option value="Follow-up">Follow-up</option>
                                        <option value="Converted">Converted</option>
                                        <option value="Not Interested">Not Interested</option>
                                        <option value="Archived">Archived</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- CRM Context (Optional Notes & Tags) -->
                        <div style="margin-bottom: 1.5rem;">
                            <h4 class="form-section-title">CRM Context</h4>
                            <div class="form-group">
                                <label for="tags">Tags (comma-separated)</label>
                                <input type="text" id="tags" name="tags" placeholder="e.g. Vendor, Client, Prospects">
                            </div>
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" style="min-height: 80px;" placeholder="Add initial conversation summary..."></textarea>
                            </div>
                        </div>

                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" id="save-btn" style="flex: 2;">SAVE CONTACT</button>
                            <button type="button" class="btn btn-secondary" id="start-over-btn-review" style="flex: 1.5;">SCAN AGAIN</button>
                            <button type="button" class="btn btn-danger" id="cancel-btn" style="flex: 1;">CANCEL</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
</div>

<!-- OCR Diagnostics Details Block (Collapsed by default) -->
<details style="margin-top: 2rem; background: var(--surface-color); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
    <summary style="cursor: pointer; font-weight: 600; padding: 0.75rem 1rem; color: var(--secondary-color); outline: none; background: var(--background-color); border-bottom: 1px solid var(--border-color); user-select: none;">🔍 Advanced Debug / Diagnostics Console</summary>
    <div class="card" id="ocr-debug-panel" style="border: none; border-radius: 0; box-shadow: none; margin-top: 0;">
        <div class="card-body" style="font-family: monospace; font-size: 0.85rem; line-height: 1.6; padding: 1.25rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px dashed var(--border-color); padding-bottom: 1rem;">
                <div>Selected File: <span id="dbg-file-selected" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>File Name: <span id="dbg-file-name" style="font-weight: bold;">-</span></div>
                <div>File Type: <span id="dbg-file-type" style="font-weight: bold;">-</span></div>
                <div>File Size: <span id="dbg-file-size" style="font-weight: bold;">-</span></div>
                <div>OCR Button Clicked: <span id="dbg-btn-clicked" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>startOCRFlow() Called: <span id="dbg-flow-called" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.5rem; margin-bottom: 1rem;">
                <div>Tesseract.js Loaded: <span id="dbg-tesseract" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>Worker Initialized: <span id="dbg-worker" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>Language Loaded (eng): <span id="dbg-lang" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>Image Validated: <span id="dbg-image" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>OCR Started: <span id="dbg-started" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
                <div>OCR Completed: <span id="dbg-completed" style="font-weight: bold; color: var(--danger-color);">NO</span></div>
            </div>
            <div>Characters Detected: <span id="dbg-chars" style="font-weight: bold; color: var(--primary-color);">0</span></div>
            <hr style="margin: 0.75rem 0; border: none; border-top: 1px solid var(--border-color);">
            <label style="font-weight: bold; display: block; margin-bottom: 0.5rem;">Raw OCR Output Plaintext:</label>
            <textarea id="dbg-raw-text" readonly style="width: 100%; min-height: 100px; font-family: monospace; font-size: 0.85rem; background-color: var(--background-color); border: 1px solid var(--border-color); padding: 0.5rem; border-radius: var(--radius-sm);" placeholder="[Raw OCR output text will be displayed here for real-time debugging]"></textarea>
        </div>
    </div>
</details>


<!-- Duplicate Modal System -->
<div id="duplicate-modal" class="modal-overlay hidden">
    <div class="modal-card">
        <div class="modal-header">
            <h3>⚠️ Possible Duplicate Contact Found</h3>
        </div>
        <div class="modal-body">
            <p id="duplicate-warning-text"></p>
            <p>What would you like to do?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" id="dup-update-btn">Update Existing</button>
            <button type="button" class="btn btn-secondary" id="dup-create-btn">Create New Anyway</button>
            <button type="button" class="btn btn-danger" id="dup-cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<!-- Extra Styles for Scanner & Dropzone -->
<style>
.dropzone-area {
    border: 3px dashed var(--border-color);
    border-radius: var(--radius-lg);
    padding: 3rem 1.5rem;
    cursor: pointer;
    background-color: var(--background-color);
    transition: var(--transition);
}
.dropzone-area:hover {
    border-color: var(--primary-color);
    background-color: rgba(79, 70, 229, 0.03);
}
.dropzone-icon {
    font-size: 3.5rem;
    margin-bottom: 1rem;
}
.image-preview-frame {
    max-width: 100%;
    max-height: 400px;
    background-color: #f1f5f9;
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--border-color);
    display: inline-block;
}
.image-preview-frame img {
    max-width: 100%;
    max-height: 400px;
    display: block;
    object-fit: contain;
}
.progress-bar-container {
    width: 100%;
    max-width: 400px;
    height: 8px;
    background-color: var(--border-color);
    border-radius: 100px;
    margin: 1.5rem auto 0 auto;
    overflow: hidden;
}
.progress-bar-fill {
    height: 100%;
    background-color: var(--primary-color);
    border-radius: 100px;
    transition: width 0.2s ease;
}
.form-section-title {
    border-left: 3px solid var(--primary-color);
    padding-left: 0.5rem;
    margin-bottom: 1rem;
    color: var(--secondary-color);
    font-size: 1rem;
    font-weight: 600;
}
/* Modal CSS */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
}
.modal-card {
    background-color: var(--surface-color);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
    width: 90%;
    max-width: 500px;
    padding: 1.75rem;
    border: 1px solid var(--border-color);
}
.modal-header h3 {
    color: var(--secondary-color);
    margin-bottom: 1rem;
    font-size: 1.25rem;
}
.modal-body {
    color: var(--text-color);
    font-size: 0.95rem;
    line-height: 1.6;
    margin-bottom: 1.5rem;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    flex-wrap: wrap;
}
</style>

<!-- Load Tesseract.js from CDN -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.3/dist/tesseract.min.js"></script>

<!-- Tesseract configuration block to support future self-hosting -->
<script>
const OCR_CONFIG = {
    // Initially uses CDN. If self-hosting locally later, change these URLs
    workerPath: 'https://cdn.jsdelivr.net/npm/tesseract.js@5.0.3/dist/worker.min.js',
    corePath: 'https://cdn.jsdelivr.net/npm/tesseract.js-core@5.0.2',
    langPath: 'https://tessdata.projectnaptha.com/4.0.0_fast'
};
</script>

<!-- Custom scanner script containing uploading + OCR + parsing heuristics + review save logic -->
<script src="assets/js/ocr.js?v=3"></script>
<script src="assets/js/parser.js?v=3"></script>
<script src="assets/js/camera.js?v=3"></script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
