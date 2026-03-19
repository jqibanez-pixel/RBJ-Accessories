"use strict";
// @ts-nocheck
(function () {
    var form = document.getElementById('dashboardFeedbackForm');
    var responseWrap = document.getElementById('dashboardFeedbackResponse');
    function setResponse(type, message) {
        if (!responseWrap)
            return;
        if (!message) {
            responseWrap.innerHTML = '';
            return;
        }
        var div = document.createElement('div');
        div.className = 'alert ' + (type === 'success' ? 'success' : 'error');
        div.textContent = message;
        responseWrap.innerHTML = '';
        responseWrap.appendChild(div);
    }
    if (!form)
        return;
    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        var submitBtn = form.querySelector('button[type="submit"]');
        var originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        }
        try {
            var formData = new FormData(form);
            var res = await fetch('dashboard.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok)
                throw new Error('Request failed');
            var data = await res.json();
            if (data.ok) {
                setResponse('success', data.message || 'Feedback submitted successfully.');
                form.reset();
            }
            else {
                setResponse('error', data.message || 'Unable to submit feedback right now.');
            }
        }
        catch (err) {
            setResponse('error', 'Could not submit feedback right now. Please try again.');
        }
        finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    });
})();
//# sourceMappingURL=user-dashboard.js.map