<?php
$page_title = 'AI Prompt Generator';
$user = $auth->getCurrentUser();

// Check if AI is available from admin settings
$has_ai_available = false;
if (isset($user)) {
    try {
        require_once __DIR__ . '/../../helpers/AIHelper.php';
        $aiHelper = AIHelper::fromAdminSettings();
        $has_ai_available = $aiHelper->isAiAvailable();
    } catch (Exception $e) {
        // Silently fail if AI components not available
        error_log("AI components not available in prompt_generator.php: " . $e->getMessage());
    }
}

// Initialize models for categories
$categoryModel = new Category();
$categories = $categoryModel->getByUserId($user['id']);

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-robot"></i> AI Prompt Generator</h2>
    <button type="button" class="btn btn-outline-primary" onclick="clearAll()">
        <i class="fas fa-eraser"></i> Clear All
    </button>
</div>

<?php if (!$has_ai_available): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>AI Features Not Available:</strong> 
    AI functionality is currently disabled or not configured. Please contact your administrator to enable AI features.
</div>
<?php else: ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-magic"></i> Generate & Enhance Prompts</h5>
            </div>
            <div class="card-body">
                <!-- Description Input -->
                <div class="mb-3">
                    <label for="promptDescription" class="form-label">Description *</label>
                    <textarea class="form-control" id="promptDescription" rows="3" 
                              placeholder="Describe what kind of prompt you want to generate..."></textarea>
                    <div class="form-text">Provide a clear description of what you want the AI to help you with.</div>
                </div>

                <!-- Generation Options -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="promptCategory" class="form-label">Category (Optional)</label>
                        <select class="form-select" id="promptCategory">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="promptStyle" class="form-label">Style</label>
                        <select class="form-select" id="promptStyle">
                            <option value="professional">Professional</option>
                            <option value="creative">Creative</option>
                            <option value="casual">Casual</option>
                            <option value="technical">Technical</option>
                            <option value="educational">Educational</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="targetAudience" class="form-label">Target Audience</label>
                        <select class="form-select" id="targetAudience">
                            <option value="general">General</option>
                            <option value="business">Business Professionals</option>
                            <option value="students">Students</option>
                            <option value="developers">Developers</option>
                            <option value="marketers">Marketers</option>
                            <option value="writers">Writers</option>
                            <option value="researchers">Researchers</option>
                        </select>
                    </div>
                </div>

                <!-- Generation Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-start mb-4">
                    <button type="button" class="btn btn-primary" onclick="generatePrompt()" id="generateBtn">
                        <i class="fas fa-robot"></i> Generate Prompt
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="generateTitle()" id="generateTitleBtn" disabled>
                        <i class="fas fa-heading"></i> Generate Title
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="generateTags()" id="generateTagsBtn" disabled>
                        <i class="fas fa-tags"></i> Generate Tags
                    </button>
                </div>

                <!-- Generated Content Area -->
                <div id="generatedContent" style="display: none;">
                    <div class="mb-3">
                        <label for="generatedTitle" class="form-label">Generated Title</label>
                        <input type="text" class="form-control" id="generatedTitle" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="generatedPromptText" class="form-label">Generated Prompt</label>
                        <textarea class="form-control" id="generatedPromptText" rows="8" readonly></textarea>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="enhancePrompt('clarity')">
                                <i class="fas fa-lightbulb"></i> Enhance for Clarity
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="enhancePrompt('creativity')">
                                <i class="fas fa-palette"></i> Enhance for Creativity
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="enhancePrompt('professionalism')">
                                <i class="fas fa-briefcase"></i> Enhance for Professionalism
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="generatedTags" class="form-label">Generated Tags</label>
                        <input type="text" class="form-control" id="generatedTags" readonly>
                    </div>

                    <!-- Save Options -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="copyToClipboard()">
                            <i class="fas fa-copy"></i> Copy to Clipboard
                        </button>
                        <button type="button" class="btn btn-success" onclick="savePrompt()">
                            <i class="fas fa-save"></i> Save as New Prompt
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- AI Tips Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Tips for Better Results</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled small">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Be specific about your goals</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Include context and background</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Mention your target audience</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Specify the desired format</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Use enhancement options to refine</li>
                </ul>
            </div>
        </div>

        <!-- Enhancement Options Card -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-magic"></i> Enhancement Options</h6>
            </div>
            <div class="card-body">
                <div class="small">
                    <p><strong>Clarity:</strong> Makes prompts clearer and more direct</p>
                    <p><strong>Creativity:</strong> Adds creative elements and inspiration</p>
                    <p><strong>Professionalism:</strong> Refines tone for business use</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
    </div>
    <div class="mt-2">Generating with AI...</div>
</div>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    color: white;
}

.prompt-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transition: all 0.2s;
}
</style>

<script>
// AI Prompt Generator JavaScript Functions

function showLoading(show = true) {
    const overlay = document.getElementById('loadingOverlay');
    overlay.style.display = show ? 'flex' : 'none';
}

function showGeneratedContent() {
    const content = document.getElementById('generatedContent');
    content.style.display = 'block';
    
    // Enable additional generation buttons
    document.getElementById('generateTitleBtn').disabled = false;
    document.getElementById('generateTagsBtn').disabled = false;
}

function generatePrompt() {
    const description = document.getElementById('promptDescription').value.trim();
    
    if (!description) {
        alert('Please enter a description for your prompt.');
        return;
    }
    
    const category = document.getElementById('promptCategory').value;
    const style = document.getElementById('promptStyle').value;
    const targetAudience = document.getElementById('targetAudience').value;
    
    showLoading(true);
    
    const formData = new FormData();
    formData.append('action', 'generate_prompt');
    formData.append('description', description);
    formData.append('category', category);
    formData.append('style', style);
    formData.append('target_audience', targetAudience);
    
    fetch('index.php?page=api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedPromptText').value = data.prompt;
            showGeneratedContent();
        } else {
            alert('Error: ' + (data.message || 'Failed to generate prompt'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to generate prompt. Please try again.');
    })
    .finally(() => {
        showLoading(false);
    });
}

function generateTitle() {
    const promptText = document.getElementById('generatedPromptText').value;
    
    if (!promptText) {
        alert('Please generate a prompt first.');
        return;
    }
    
    showLoading(true);
    
    const formData = new FormData();
    formData.append('action', 'generate_title');
    formData.append('prompt', promptText);
    
    fetch('index.php?page=api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedTitle').value = data.title;
        } else {
            alert('Error: ' + (data.message || 'Failed to generate title'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to generate title. Please try again.');
    })
    .finally(() => {
        showLoading(false);
    });
}

function generateTags() {
    const promptText = document.getElementById('generatedPromptText').value;
    
    if (!promptText) {
        alert('Please generate a prompt first.');
        return;
    }
    
    showLoading(true);
    
    const formData = new FormData();
    formData.append('action', 'generate_tags');
    formData.append('prompt', promptText);
    
    fetch('index.php?page=api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedTags').value = data.tags;
        } else {
            alert('Error: ' + (data.message || 'Failed to generate tags'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to generate tags. Please try again.');
    })
    .finally(() => {
        showLoading(false);
    });
}

function enhancePrompt(enhancementType) {
    const promptText = document.getElementById('generatedPromptText').value;
    
    if (!promptText) {
        alert('Please generate a prompt first.');
        return;
    }
    
    showLoading(true);
    
    const formData = new FormData();
    formData.append('action', 'enhance_prompt');
    formData.append('prompt', promptText);
    formData.append('enhancement_type', enhancementType);
    
    fetch('index.php?page=api', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('generatedPromptText').value = data.enhanced_prompt;
        } else {
            alert('Error: ' + (data.message || 'Failed to enhance prompt'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to enhance prompt. Please try again.');
    })
    .finally(() => {
        showLoading(false);
    });
}

function copyToClipboard() {
    const promptText = document.getElementById('generatedPromptText').value;
    
    if (!promptText) {
        alert('No prompt to copy.');
        return;
    }
    
    navigator.clipboard.writeText(promptText).then(() => {
        // Show success feedback
        const button = event.target;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="fas fa-check"></i> Copied!';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-secondary');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard.');
    });
}

function savePrompt() {
    const title = document.getElementById('generatedTitle').value || 'AI Generated Prompt';
    const promptText = document.getElementById('generatedPromptText').value;
    const tags = document.getElementById('generatedTags').value;
    const category = document.getElementById('promptCategory').value;
    
    if (!promptText) {
        alert('No prompt to save.');
        return;
    }
    
    // Create form to submit to prompts page
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'index.php?page=prompts';
    
    // Add form fields
    const fields = {
        'action': 'create',
        'title': title,
        'content': promptText,
        'tags': tags,
        'category_id': category ? getCategoryId(category) : ''
    };
    
    Object.keys(fields).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = fields[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}

function getCategoryId(categoryName) {
    const select = document.getElementById('promptCategory');
    const options = select.options;
    
    for (let i = 0; i < options.length; i++) {
        if (options[i].text === categoryName) {
            return options[i].value;
        }
    }
    return select.value; // Return current selected value as fallback
}

function clearAll() {
    if (confirm('Are you sure you want to clear all fields?')) {
        document.getElementById('promptDescription').value = '';
        document.getElementById('promptCategory').value = '';
        document.getElementById('promptStyle').value = 'professional';
        document.getElementById('targetAudience').value = 'general';
        document.getElementById('generatedTitle').value = '';
        document.getElementById('generatedPromptText').value = '';
        document.getElementById('generatedTags').value = '';
        document.getElementById('generatedContent').style.display = 'none';
        document.getElementById('generateTitleBtn').disabled = true;
        document.getElementById('generateTagsBtn').disabled = true;
    }
}
</script>

<?php
$content = ob_get_clean();
include 'layout.php';
?>