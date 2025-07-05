// assets/js/main.js

function togglePostForm() {
    const form = document.getElementById('new-post-form');
    if (form.style.display === 'none' || form.style.display === '') {
        form.style.display = 'block';
    } else {
        form.style.display = 'none';
    }
}

// Example: Future enhancements for like buttons, etc.
document.addEventListener('DOMContentLoaded', function() {
    const likeButtons = document.querySelectorAll('.like-btn');
    likeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            console.log('Liked post: ' + postId);
        });
    });
});
