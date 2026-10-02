document.addEventListener('DOMContentLoaded', () => {
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach((link) => {
        const targetPage = link.getAttribute('href');
        if (targetPage === currentPage) {
            link.classList.add('active');
        }
    });

    if (document.body.classList.contains('manager-layout')) {
        const managerLinks = document.querySelectorAll('.nav-link[href^="#"]');
        const managerSections = Array.from(managerLinks)
            .map((link) => document.querySelector(link.getAttribute('href')))
            .filter(Boolean);

        const setManagerActive = (targetId) => {
            managerLinks.forEach((link) => {
                const isActive = link.getAttribute('href') === `#${targetId}`;
                link.classList.toggle('active', isActive);
                if (isActive) {
                    link.setAttribute('aria-current', 'page');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        };

        managerLinks.forEach((link) => {
            link.addEventListener('click', () => {
                setManagerActive(link.getAttribute('href').slice(1));
            });
        });

        if (managerSections.length > 0 && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                const visibleSection = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((first, second) => second.intersectionRatio - first.intersectionRatio)[0];
                if (visibleSection) {
                    setManagerActive(visibleSection.target.id);
                }
            }, { rootMargin: '-18% 0px -65% 0px', threshold: [0.1, 0.5, 1] });

            managerSections.forEach((section) => observer.observe(section));
        }
    }

    const modal = document.getElementById('editSectionModal');
    const modalFields = document.getElementById('editFieldsContainer');
    const sectionField = document.getElementById('updateSectionField');
    const closeModalBtn = document.querySelector('.close-edit-modal');
    const cancelEditBtn = document.getElementById('cancelEditBtn');

    const sectionMap = {
        basic: {
            label: 'Basic Information',
            fields: [
                ['name', 'Full Name', 'text'],
                ['phone', 'Phone Number', 'text'],
                ['birthday', 'Birthday', 'date'],
                ['address', 'Address', 'text'],
                ['civil_status', 'Civil Status', 'text']
            ]
        },
        government: {
            label: 'Government Information',
            fields: [
                ['sss_number', 'SSS Number', 'text'],
                ['philhealth_number', 'PhilHealth Number', 'text'],
                ['pagibig_number', 'Pag-IBIG Number', 'text'],
                ['tin_number', 'TIN', 'text'],
                ['tax_status', 'Tax Status', 'text'],
                ['emergency_contact', 'Emergency Contact', 'text']
            ]
        },
        work: {
            label: 'Work Information',
            fields: [
                ['department', 'Department', 'text'],
                ['role', 'Role', 'text'],
                ['manager', 'Manager', 'text'],
                ['employment_type', 'Employment Type', 'text'],
                ['location', 'Location', 'text'],
                ['join_date', 'Join Date', 'date'],
                ['salary', 'Monthly Salary', 'number']
            ]
        },
        schedule: {
            label: 'Work Schedule',
            fields: [
                ['schedule_type', 'Schedule Type', 'text'],
                ['shift_time', 'Shift Time', 'text'],
                ['work_days', 'Work Days', 'text'],
                ['rest_day', 'Rest Day', 'text'],
                ['attendance_notes', 'Attendance Notes', 'text']
            ]
        }
    };

    const fillFields = (section) => {
        if (!modal || !modalFields || !sectionField || !sectionMap[section]) return;

        const entry = sectionMap[section];
        const employee = document.body.dataset.employee || '{}';
        let employeeData = {};

        try {
            employeeData = JSON.parse(employee);
        } catch (error) {
            employeeData = {};
        }

        sectionField.value = section;
        modalFields.innerHTML = '';

        entry.fields.forEach(([name, label, type]) => {
            const fieldWrapper = document.createElement('label');
            fieldWrapper.textContent = label;

            const input = document.createElement(type === 'number' ? 'input' : 'input');
            input.type = type;
            input.name = name;
            input.value = employeeData[name] || '';
            fieldWrapper.appendChild(input);
            modalFields.appendChild(fieldWrapper);
        });
    };

    document.querySelectorAll('.section-edit-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const section = button.dataset.section;
            fillFields(section);
            if (modal) modal.classList.remove('hidden');
        });
    });

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            if (modal) modal.classList.add('hidden');
        });
    }

    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', () => {
            if (modal) modal.classList.add('hidden');
        });
    }

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
    }

    document.querySelectorAll('form').forEach((form) => {
        const actionInput = form.querySelector('input[name="attendance_action"]');
        if (!actionInput) {
            return;
        }

        form.addEventListener('submit', () => {
            const action = actionInput.value;
            const message = action === 'clock_in'
                ? 'You are clocked in successfully.'
                : 'You are clocked out successfully.';

            const existingFlash = document.querySelector('.dashboard-notice');
            if (!existingFlash) {
                const notice = document.createElement('div');
                notice.className = 'alert alert-success dashboard-notice';
                notice.textContent = message;
                const panel = document.querySelector('.main-panel');
                if (panel) {
                    panel.insertBefore(notice, panel.firstChild.nextSibling);
                }
            }
        });
    });

    const forms = document.querySelectorAll('[data-form]');
    forms.forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelector('button[type="submit"]').disabled = true;
            form.querySelector('button[type="submit"]').textContent = 'Submitting...';
        });
    });
});
