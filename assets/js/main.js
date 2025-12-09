document.addEventListener('DOMContentLoaded', function() {
    initSearchFunctionality();
    initRatingSystem();
    initCommentActions();
    initCookieBanner();
    initToasts();
});

function initSearchFunctionality() {
    const searchInput = document.getElementById('searchInput');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                hideSearchResults();
                return;
            }

            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                hideSearchResults();
            }
        });
    }
}

function performSearch(query) {
    const searchResults = document.getElementById('searchResults');
    
    searchResults.innerHTML = '<div class="p-3 text-center"><div class="loading-spinner mx-auto"></div></div>';
    searchResults.style.display = 'block';

    fetch('search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'query=' + encodeURIComponent(query)
    })
    .then(response => response.json())
    .then(data => {
        displaySearchResults(data);
    })
    .catch(error => {
        console.error('Грешка при търсенето:', error);
        searchResults.innerHTML = '<div class="p-3 text-danger">Грешка при търсенето</div>';
    });
}

function displaySearchResults(results) {
    const searchResults = document.getElementById('searchResults');
    
    if (results.length === 0) {
        searchResults.innerHTML = '<div class="p-3 text-muted">Няма намерени резултати</div>';
        return;
    }

    let html = '';
    results.forEach(result => {
        html += `
            <div class="search-result-item d-flex align-items-center" onclick="goToAnime(${result.id})">
                <img src="${result.banner_image || 'assets/img/default-anime.jpg'}" 
                     class="search-result-img me-3" alt="${result.title}">
                <div class="flex-grow-1">
                    <h6 class="mb-1">${result.title}</h6>
                    <small class="text-muted">${result.genre}</small>
                    ${result.description ? `<p class="mb-0 small">${result.description.substring(0, 80)}...</p>` : ''}
                </div>
            </div>
        `;
    });

    searchResults.innerHTML = html;
}

function hideSearchResults() {
    const searchResults = document.getElementById('searchResults');
    if (searchResults) {
        searchResults.style.display = 'none';
    }
}

function goToAnime(id) {
    window.location.href = `anime.php?id=${animeId}`;
}

function initRatingSystem() {
    const ratingInputs = document.querySelectorAll('.rating-input input[type="radio"]');
    
    ratingInputs.forEach(input => {
        input.addEventListener('change', function() {
            const animeId = this.dataset.animeId;
            const rating = this.value;
            
            submitRating(animeId, rating);
        });
    });
}

function sendRating(animeId, rating) {
    fetch('rate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `anime_id=${animeId}&rating=${rating}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Благодарим за оценката!', 'success');
            updateRatingDisplay(data.newAverage, data.totalRatings);
        } else {
            showToast(data.message || 'Грешка при гласуването', 'error');
        }
    })
    .catch(error => {
        console.error('Грешка при гласуването:', error);
        showToast('Грешка при гласуването', 'error');
    });
}

function updateRatingDisplay(average, total) {
    const avgElement = document.getElementById('averageRating');
    const totalElement = document.getElementById('totalRatings');
    
    if (avgElement) {
        avgElement.textContent = average;
    }
    
    if (totalElement) {
        totalElement.textContent = total;
    }
}

function initCommentActions() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('like-btn') || e.target.closest('.like-btn')) {
            e.preventDefault();
            const btn = e.target.closest('.like-btn');
            const commentId = btn.dataset.commentId;
            
            toggleCommentLike(commentId, btn);
        }
    });
}

function toggleCommentLike(commentId) {
    fetch('comment_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=toggle_like&comment_id=${commentId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.classList.toggle('liked');
            const countSpan = button.querySelector('.like-count');
            if (countSpan) {
                countSpan.textContent = data.likeCount;
            }
            
            const icon = button.querySelector('i');
            if (data.liked) {
                icon.className = 'bi bi-heart-fill';
            } else {
                icon.className = 'bi bi-heart';
            }
        } else {
            showToast(data.message || 'Грешка при харесването', 'error');
        }
    })
    .catch(error => {
        console.error('Грешка при харесването:', error);
        showToast('Грешка при харесването', 'error');
    });
}

function initCookieBanner() {
    const cookieBanner = document.getElementById('cookieBanner');
    if (cookieBanner && !getCookie('cookies_accepted')) {
        cookieBanner.style.display = 'block';
    }
}

function acceptCookies() {
    setCookie('cookies_accepted', 'true', 365);
    const cookieBanner = document.getElementById('cookieBanner');
    if (cookieBanner) {
        cookieBanner.style.display = 'none';
    }
}

function setCookie(name, value, days) {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for(let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) == ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

function initToasts() {
    if (!document.querySelector('.toast-container')) {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
}

function showToast(message, type = 'info') {
    const container = document.querySelector('.toast-container');
    const toastId = 'toast-' + Date.now();
    
    const toastHTML = `
        <div id="${toastId}" class="toast ${type}" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">
                    ${type === 'success' ? '✅' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}
                    ${type === 'success' ? 'Успех' : type === 'error' ? 'Грешка' : type === 'warning' ? 'Внимание' : 'Информация'}
                </strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
    });
    
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

function addComment(discussionId, content) {
    if (!content.trim()) {
        showToast('Моля въведете съдържание на коментара', 'warning');
        return;
    }

    fetch('comment_actions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=add_comment&discussion_id=${discussionId}&content=${encodeURIComponent(content)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Коментарът е добавен успешно!', 'success');
            setTimeout(() => {
                location.reload();
            }, 1000);
        } else {
            showToast(data.message || 'Грешка при добавянето на коментара', 'error');
        }
    })
    .catch(error => {
        console.error('Грешка при добавянето на коментара:', error);
        showToast('Грешка при добавянето на коментара', 'error');
    });
}

function validateForm(form) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    
    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.querySelector(`[onclick="togglePasswordVisibility('${inputId}')"] i`);
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

function previewImage(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.style.display = 'none';
    }
}

function autoResizeTextarea() {
    const textareas = document.querySelectorAll('textarea.auto-resize');
    
    textareas.forEach(textarea => {
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    });
}

function initScrollAnimations() {
    const elements = document.querySelectorAll('.fade-in-up');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, {
        threshold: 0.1
    });
    
    elements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });
}

document.addEventListener('DOMContentLoaded', initScrollAnimations);

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});