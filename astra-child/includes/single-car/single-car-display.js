/**
 * Single Car Display JavaScript - Separated for better organization
 * Main car functionality (thumbnails, read more, favorites, specs toggle)
 */

document.addEventListener('DOMContentLoaded', function() {
    const thumbnails = document.querySelectorAll('.thumbnail');
    const mainImage = document.querySelector('.main-image img');
    
    const preloadImages = () => {
        thumbnails.forEach(thumb => {
            const fullUrl = thumb.dataset.fullUrl;
            if (fullUrl) {
                const img = new Image();
                img.src = fullUrl;
            }
        });
    };
    
    preloadImages();

    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            thumbnails.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            const newImageUrl = this.dataset.fullUrl;
            if (newImageUrl && mainImage) {
                mainImage.src = newImageUrl;
            }
        });
    });

    const readMoreBtn = document.querySelector('.read-more-btn');
    if (readMoreBtn) {
        readMoreBtn.addEventListener('click', function() {
            const descriptionText = document.querySelector('.description-text');
            const fullDescription = document.querySelector('.full-description');
            
            if (descriptionText && fullDescription) {
                if (descriptionText.style.display === 'none') {
                    descriptionText.style.display = 'block';
                    fullDescription.style.display = 'none';
                    this.textContent = 'Read more';
                } else {
                    descriptionText.style.display = 'none';
                    fullDescription.style.display = 'block';
                    this.textContent = 'Show less';
                }
            }
        });
    }

    const favoriteBtn = document.querySelector('.favorite-btn');
    if (favoriteBtn) {
        favoriteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
                alert('Please log in to add favorites. (Error: Script data missing)');
                return;
            }

            const carId = this.getAttribute('data-car-id');
            const isActive = this.classList.contains('active');
            const heartIcon = this.querySelector('i');
            const favoriteText = this.querySelector('.favorite-text');

            this.classList.toggle('active');
            if (isActive) {
                heartIcon.classList.remove('fas');
                heartIcon.classList.add('far');
                if (favoriteText) favoriteText.textContent = 'Add to Favorites';
            } else {
                heartIcon.classList.remove('far');
                heartIcon.classList.add('fas');
                if (favoriteText) favoriteText.textContent = 'Remove from Favorites';
            }

            const formData = new FormData();
            formData.append('action', 'toggle_favorite_car');
            formData.append('car_id', carId);
            formData.append('is_favorite', !isActive ? '1' : '0');
            formData.append('nonce', carListingsData.nonce);

            fetch(carListingsData.ajaxurl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok.');
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    this.classList.toggle('active');
                    if (isActive) {
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                        if (favoriteText) favoriteText.textContent = 'Remove from Favorites';
                    } else {
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                        if (favoriteText) favoriteText.textContent = 'Add to Favorites';
                    }
                    console.error('Favorite toggle failed:', data);
                    alert('Failed to update favorites. Please try again.');
                }
            })
            .catch(error => {
                this.classList.toggle('active');
                if (isActive) {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                    if (favoriteText) favoriteText.textContent = 'Remove from Favorites';
                } else {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                    if (favoriteText) favoriteText.textContent = 'Add to Favorites';
                }
                console.error('Error:', error);
                alert('Failed to update favorites. An error occurred.');
            });
        });
    }

    const toggleBtn = document.querySelector('.specs-features-toggle');
    const content = document.querySelector('.specs-features-content');
    
    if (toggleBtn && content) {
        toggleBtn.addEventListener('click', function() {
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            toggleBtn.classList.toggle('active');
        });
    }
}); 