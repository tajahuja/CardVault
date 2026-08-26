        </main>
    </div>
    
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- MODAL: WHATSAPP COMPOSER -->
        <div id="whatsapp-composer-modal" class="modal-overlay hidden">
            <div class="modal-card">
                <div class="modal-header">
                    <h3>💬 WhatsApp Follow-up Composer</h3>
                </div>
                <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div>
                            <label style="font-size: 0.8rem; color: var(--text-muted);">Recipient</label>
                            <div id="wa-recipient-name" style="font-weight: 600; font-size: 0.95rem; margin-top: 0.15rem;">-</div>
                        </div>
                        <div>
                            <label style="font-size: 0.8rem; color: var(--text-muted);">WhatsApp Number</label>
                            <div id="wa-recipient-phone" style="font-weight: 600; font-size: 0.95rem; margin-top: 0.15rem; color: var(--primary-color);">-</div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="wa-composer-message">Message Template</label>
                        <textarea id="wa-composer-message" style="min-height: 120px; font-size: 0.9rem; margin-bottom: 0; padding: 0.5rem; width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-family: inherit; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeComposerModal('whatsapp-composer-modal')">Cancel</button>
                    <button type="button" class="btn btn-primary" style="background-color: #25d366; border-color: #20ba5a;" id="wa-send-btn">🚀 Open WhatsApp</button>
                </div>
            </div>
        </div>

        <!-- MODAL: EMAIL COMPOSER -->
        <div id="email-composer-modal" class="modal-overlay hidden">
            <div class="modal-card">
                <div class="modal-header">
                    <h3>✉️ Email Follow-up Composer</h3>
                </div>
                <div class="modal-body" style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div>
                            <label style="font-size: 0.8rem; color: var(--text-muted);">Recipient</label>
                            <div id="email-recipient-name" style="font-weight: 600; font-size: 0.95rem; margin-top: 0.15rem;">-</div>
                        </div>
                        <div>
                            <label style="font-size: 0.8rem; color: var(--text-muted);">Email Address</label>
                            <div id="email-recipient-address" style="font-weight: 600; font-size: 0.95rem; margin-top: 0.15rem; color: var(--primary-color);">-</div>
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label for="email-composer-subject">Subject</label>
                        <input type="text" id="email-composer-subject" style="font-size: 0.9rem; margin-bottom: 0; padding: 0.5rem; width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-family: inherit;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="email-composer-message">Message Body</label>
                        <textarea id="email-composer-message" style="min-height: 140px; font-size: 0.9rem; margin-bottom: 0; padding: 0.5rem; width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-sm); font-family: inherit; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeComposerModal('email-composer-modal')">Cancel</button>
                    <button type="button" class="btn btn-primary" id="email-send-btn">🚀 Open Email Client</button>
                </div>
            </div>
        </div>

        <script>
            const _SESSION_USER_NAME = <?php echo json_encode($_SESSION['user_name'] ?? ''); ?>;

            window.openComposerModal = function(id) {
                document.getElementById(id).classList.remove('hidden');
            }
            window.closeComposerModal = function(id) {
                document.getElementById(id).classList.add('hidden');
            }

            // Global WhatsApp Composer trigger
            window.triggerWhatsAppComposer = function(contactId, name, phone, company, placeMet) {
                document.getElementById('wa-recipient-name').textContent = name;
                document.getElementById('wa-recipient-phone').textContent = phone;
                
                const placeStr = placeMet ? ` at ${placeMet}` : '';
                const companyStr = company ? ` from ${company}` : '';
                const suggestedMsg = `Hi ${name}, it was great connecting with you recently${placeStr}${companyStr}. Let's stay in touch! - ${_SESSION_USER_NAME} via CardVault`;
                
                document.getElementById('wa-composer-message').value = suggestedMsg;
                
                document.getElementById('wa-send-btn').onclick = function() {
                    const message = document.getElementById('wa-composer-message').value;
                    const cleanPhone = phone.replace(/\D/g, '');
                    const waUrl = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(message)}`;
                    
                    logComposerInteraction(contactId, 'WhatsApp', `Opened WhatsApp follow-up chat. Message: "${message}"`);
                    
                    window.open(waUrl, '_blank');
                    closeComposerModal('whatsapp-composer-modal');
                };
                
                openComposerModal('whatsapp-composer-modal');
            }

            // Global Email Composer trigger
            window.triggerEmailComposer = function(contactId, name, email, company, placeMet) {
                document.getElementById('email-recipient-name').textContent = name;
                document.getElementById('email-recipient-address').textContent = email;
                
                const placeStr = placeMet ? ` at ${placeMet}` : '';
                const subject = `Great connecting with you, ${name}`;
                const suggestedBody = `Hi ${name},\n\nIt was great meeting you recently${placeStr}.\n\nLet's coordinate a time to speak.\n\nBest regards,\n${_SESSION_USER_NAME}`;
                
                document.getElementById('email-composer-subject').value = subject;
                document.getElementById('email-composer-message').value = suggestedBody;
                
                document.getElementById('email-send-btn').onclick = function() {
                    const sub = document.getElementById('email-composer-subject').value;
                    const body = document.getElementById('email-composer-message').value;
                    const mailtoUrl = `mailto:${email}?subject=${encodeURIComponent(sub)}&body=${encodeURIComponent(body)}`;
                    
                    logComposerInteraction(contactId, 'Email', `Opened email composer follow-up client. Subject: "${sub}"`);
                    
                    window.open(mailtoUrl, '_blank');
                    closeComposerModal('email-composer-modal');
                };
                
                openComposerModal('email-composer-modal');
            }

            function logComposerInteraction(contactId, type, description) {
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                const formData = new FormData();
                formData.append('contact_id', contactId);
                formData.append('type', type);
                formData.append('description', description);
                formData.append('csrf_token', csrfToken);
                
                fetch('api/log_interaction.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${type} interaction logged to timeline.`, 'success');
                    }
                })
                .catch(err => console.error(err));
            }
        </script>
    <?php endif; ?>

    <?php echo $additionalFoot ?? ''; ?>
</body>
</html>
