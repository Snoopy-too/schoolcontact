<?php
require 'config.php';
$csrf_token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>無料体験レッスン予約 | Free Trial Booking Reservation</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans JP', sans-serif; background-color: #f8f9fa; }
        .wizard-step { display: none; }
        .wizard-step.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .btn-xl { padding: 1rem 2rem; font-size: 1.15rem; width: 100%; margin-bottom: 0.8rem; text-align: left; }
        .slot-card { cursor: pointer; transition: transform 0.2s; border: 2px solid transparent; }
        .slot-card:hover { transform: scale(1.02); border-color: #0d6efd; }
        .slot-card.selected { border-color: #0d6efd; background-color: #e9ecef; }
        .hide-if-no-slots { display: none; }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <img src="images/logo.png" alt="School Logo" style="max-height: 150px;">
                    </div>
                    <h2 class="text-center mb-4">無料体験レッスン予約</h2>

                    <div class="text-center mb-4">
                        <a href="https://pierenglishschool.com/" target="_blank" class="btn btn-outline-info">
                            Visit Our Website
                        </a>
                    </div>

                    <div class="progress mb-4" style="height: 5px;">
                        <div class="progress-bar" role="progressbar" style="width: 0%" id="progressBar"></div>
                    </div>

                    <form id="bookingForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="website" value="">
                        <!-- We store the final logic-derived info here -->
                        <input type="hidden" name="slot_id" id="inputSlotId">
                        <input type="hidden" name="level" id="inputLevel">      <!-- Stores Grade or Level string -->
                        <input type="hidden" name="audiences" id="inputAudiences"> <!-- JSON string of array -->

                        <!-- Step 1: Who is this for? -->
                        <div class="wizard-step active" id="step1">
                            <h4 class="text-center mb-4">誰のためのレッスンですか？<br><small class="text-muted text-small fs-6">Who is this for?</small></h4>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary btn-xl" onclick="goToStep2('kid')">
                                    👦 子供 (Kids)
                                </button>
                                <button type="button" class="btn btn-outline-success btn-xl" onclick="goToStep2('adult')">
                                    👨 大人 (Adults)
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Grade/Level Selection -->
                        <div class="wizard-step" id="step2">
                            <h4 class="text-center mb-4" id="step2Title">質問</h4>
                            <div id="step2Options" class="d-grid gap-2">
                                <!-- Populated by JS -->
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-link link-secondary" onclick="prevStep(1)">戻る (Back)</button>
                            </div>
                        </div>

                        <!-- Step 3: Slot Selection -->
                        <div class="wizard-step" id="step3">
                            <h4 class="text-center mb-3">ご希望の日時を選択<br><small class="text-muted fs-6">Select a Time Slot</small></h4>
                            
                            <div id="slotsContainer" class="d-grid gap-2">
                                <!-- Loaded via AJAX -->
                            </div>

                            <div id="waitlistMessage" class="alert alert-warning mt-3 text-center" style="display:none;">
                                <!-- Populated dynamically -->
                            </div>

                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-link link-secondary" onclick="prevStep(2)">戻る (Back)</button>
                            </div>
                        </div>

                        <!-- Step 4: Contact Info -->
                        <div class="wizard-step" id="step4">
                            <h4 class="text-center mb-3">お客様情報<br><small class="text-muted fs-6">Contact Info</small></h4>
                            <div class="mb-3">
                                <label for="name" class="form-label">お名前 (Name)</label>
                                <input type="text" class="form-control" id="name" name="name" required placeholder="山田 太郎">
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">メールアドレス (Email) <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required placeholder="email@example.com">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">電話番号 (Phone) <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required placeholder="090-1234-5678">
                            </div>
                            
                            <div class="alert alert-info small" id="confirmationNote">
                                ※予約確定後、詳細な情報をメールでお送りします。
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">送信する (Submit)</button>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-link link-secondary" onclick="prevStep(3)">戻る (Back)</button>
                            </div>
                        </div>

                        <!-- Step 5: Success -->
                        <div class="wizard-step" id="step5">
                            <div class="text-center text-success py-5">
                                <h1 class="display-4">🎉</h1>
                                <h3 id="successTitle">送信完了しました！</h3>
                                <p id="successMsg">Sent Successfully.</p>
                                <p class="text-muted">当日はお気をつけてお越しください。</p>
                                <a href="index.php" class="btn btn-outline-primary mt-3">トップに戻る</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    let currentStep = 1;
    let selectedType = ''; // 'kid' or 'adult'

    function showStep(step) {
        $('.wizard-step').removeClass('active');
        $(`#step${step}`).addClass('active');
        currentStep = step;
        let progress = ((step - 1) / 4) * 100;
        $('#progressBar').css('width', `${progress}%`);
    }

    function prevStep(targetStep) {
        if (targetStep === 2) {
            setupStep2(selectedType); // Re-render if going back
        }
        showStep(targetStep);
    }

    // --- Logic for Step 2 ---
    function goToStep2(type) {
        selectedType = type;
        setupStep2(type);
        showStep(2);
    }

    function setupStep2(type) {
        const container = $('#step2Options');
        container.empty();
        
        if (type === 'kid') {
            $('#step2Title').html('何年生ですか？<br><small class="text-muted fs-6">What grade are you in?</small>');
            
            const grades = [
                { label: '幼稚園 (Kindergarten)', val: 'Kindergarten', audiences: ['Kids New'] },
                { label: '小学1年生 (1st Grade)', val: '1st Grade', audiences: ['Kids 1st'] },
                { label: '2年生 (2nd Grade)', val: '2nd Grade', audiences: ['Kids 2nd'] },
                { label: '3年生 (3rd Grade)', val: '3rd Grade', audiences: ['Kids 3rd'] },
                { label: '4年生 (4th Grade)', val: '4th Grade', audiences: ['Kids 4th'] },
                { label: '5年生 / 6年生 (5th/6th Grade)', val: '5th/6th Grade', audiences: ['Kids 5-6'] },
                { label: '中学生 (Jr. High)', val: 'Jr. High', audiences: ['Jr. High'] },
                { label: '高校生 (High School)', val: 'High School', audiences: ['High Sch'] }
            ];

            grades.forEach(g => {
                container.append(`<button type="button" class="btn btn-outline-secondary btn-xl" ` +
                    `onclick='selectGrade("${g.val}", ${JSON.stringify(g.audiences)})'>${g.label}</button>`);
            });

        } else {
            $('#step2Title').html('現在の英語レベルは？<br><small class="text-muted fs-6">Current English Level?</small>');
            
            const levels = [
                { label: '🔰 初心者 (Beginner)', val: 'Beginner', audiences: ['Adult (L)'] },
                { label: '🗣️ 中級・上級 (Intermediate/Advanced)', val: 'Intermediate/Advanced', audiences: ['Adult (H)'] }
            ];

            levels.forEach(l => {
                container.append(`<button type="button" class="btn btn-outline-secondary btn-xl" ` +
                    `onclick='selectGrade("${l.val}", ${JSON.stringify(l.audiences)})'>${l.label}</button>`);
            });
        }
    }

    // --- selection handler ---
    function selectGrade(label, audiences) {
        $('#inputLevel').val(label); // Store readable grade/level
        // We will pass audiences to fetch slots
        loadSlots(audiences);
        showStep(3);
    }

    // --- Step 3: Load Slots ---
    function loadSlots(audiences) {
        const container = $('#slotsContainer');
        const waitlistDiv = $('#waitlistMessage');
        
        container.html('<div class="text-center py-5"><div class="spinner-border text-primary"></div></div>');
        waitlistDiv.hide();
        $('#inputSlotId').val(''); // Reset

        // Construct query params: ?audience[]=A&audience[]=B
        let queryString = audiences.map(a => `audience[]=${encodeURIComponent(a)}`).join('&');

        $.ajax({
            url: 'get_slots.php?' + queryString,
            method: 'GET',
            success: function(response) {
                container.empty();
                
                if (response.slots.length === 0) {
                    const grade = $('#inputLevel').val();
                    waitlistDiv.html(`
                        <p class="mb-1 fw-bold">現在、${grade}の空き枠がありません。</p>
                        <p class="mb-0 text-muted small">There are currently no openings for ${grade}.</p>
                    `).show();
                    return;
                }

                response.slots.forEach(slot => {
                    const html = `
                        <div class="card slot-card p-3 mb-2" onclick="selectSlot(${slot.id}, this)">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0 fw-bold">${slot.display_datetime}</h5>
                                </div>
                                <i class="bi bi-chevron-right text-muted">></i>
                            </div>
                        </div>
                    `;
                    container.append(html);
                });
            },
            error: function() {
                container.html('<div class="alert alert-danger">読み込みエラーが発生しました。</div>');
            }
        });
    }

    function selectSlot(id, element) {
        $('#inputSlotId').val(id);
        $('.slot-card').removeClass('selected bg-light border-primary');
        $(element).addClass('selected bg-light border-primary');
        setTimeout(() => { showStep(4); }, 300);
    }

    function skipSlotSelection() {
        $('#inputSlotId').val(''); // Empty for waitlist
        showStep(4);
    }

    // --- Step 4: Submit ---
    $('#bookingForm').on('submit', function(e) {
        e.preventDefault();
        
        const btn = $('#submitBtn');
        const originalText = btn.text();
        btn.prop('disabled', true).text('Processing...');

        const formData = {
            csrf_token: $('input[name="csrf_token"]').val(),
            website: $('input[name="website"]').val(),
            slot_id: $('#inputSlotId').val(), // Can be empty
            level: $('#inputLevel').val(),   // Contains Grade or Level
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val()
        };

        $.ajax({
            url: 'book_slot.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                if (response.success) {
                    if (!formData.slot_id) {
                        $('#successTitle').text('受付しました');
                        $('#successMsg').text('We have received your inquiry. We will contact you soon.');
                    }
                    showStep(5);
                }
            },
            error: function(xhr) {
                let msg = 'Error occurred.';
                if (xhr.responseJSON && xhr.responseJSON.error) msg += '\n' + xhr.responseJSON.error;
                alert(msg);
                btn.prop('disabled', false).text(originalText);
            }
        });
    });
</script>

</body>
</html>
