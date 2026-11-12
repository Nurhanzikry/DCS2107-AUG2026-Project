document.addEventListener('DOMContentLoaded', () => {
    // Mobile navigation toggle.
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.querySelector('.main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Ask before deleting records or cancelling a registration.
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'Are you sure you want to continue?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });

    // Character counters for long descriptions.
    document.querySelectorAll('textarea[data-counter]').forEach((textarea) => {
        const counter = textarea.parentElement.querySelector('.counter-text');
        const update = () => {
            if (counter) counter.textContent = `${textarea.value.length} characters`;
        };
        textarea.addEventListener('input', update);
        update();
    });

    // Basic browser-side validation for event capacity and dates.
    document.querySelectorAll('form[data-validate="event-form"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const dateInput = form.querySelector('input[name="event_date"]');
            const capacityInput = form.querySelector('input[name="capacity"]');
            if (capacityInput && (!Number.isInteger(Number(capacityInput.value)) || Number(capacityInput.value) < 1)) {
                event.preventDefault();
                window.alert('Capacity must be a positive whole number.');
                capacityInput.focus();
                return;
            }
            const today = new Date();
            const localToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            if (dateInput && dateInput.value && dateInput.value < localToday) {
                // Existing events can be marked completed/cancelled; only block old dates while upcoming.
                const status = form.querySelector('select[name="status"]');
                if (!status || status.value === 'upcoming') {
                    event.preventDefault();
                    window.alert('An upcoming event cannot have a past date.');
                    dateInput.focus();
                }
            }
        });
    });

    // Show student-only fields only when creating/editing a student account.
    document.querySelectorAll('form[data-validate="user-form"]').forEach((form) => {
        const roleSelect = form.querySelector('select[name="role"]');
        const studentFields = form.querySelector('.student-fields');
        const studentNumber = form.querySelector('input[name="student_number"]');
        const programme = form.querySelector('input[name="programme"]');
        const updateStudentFields = () => {
            if (!roleSelect || !studentFields) return;
            const isStudent = roleSelect.value === 'student';
            studentFields.hidden = !isStudent;
            if (studentNumber) studentNumber.required = isStudent;
            if (programme) programme.required = isStudent;
        };
        if (roleSelect) roleSelect.addEventListener('change', updateStudentFields);
        updateStudentFields();
    });

    // Public account forms get a small password-match check before PHP validation.
    document.querySelectorAll('form[data-validate="student-registration"]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const password = form.querySelector('input[name="password"]');
            const confirm = form.querySelector('input[name="confirm_password"]');
            if (password && confirm && password.value !== confirm.value) {
                event.preventDefault();
                window.alert('The passwords do not match.');
                confirm.focus();
            }
        });
    });
});
