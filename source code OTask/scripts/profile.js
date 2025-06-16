document.addEventListener('DOMContentLoaded', () => {
    const editNameIcon = document.getElementById('edit-name');
    const editPasswordIcon = document.getElementById('edit-password');
    const editProfilePicIcon = document.getElementById('edit-profile-pic');
    const logoutButton = document.getElementById('logout-button');

    const editModal = document.getElementById('edit-modal');
    const logoutConfirmModal = document.getElementById('logout-confirm-modal');

    const closeModalButtons = document.querySelectorAll('.close-button');
    const modalTitle = document.getElementById('modal-title');
    const modalInput = document.getElementById('modal-input');
    const modalConfirmInput = document.getElementById('modal-confirm-input');
    const modalFileInput = document.getElementById('modal-file-input');
    const modalUpdateButton = document.getElementById('modal-update-button');
    const modalInputPasswordToggle = document.getElementById('modal-input-password-toggle');
    const modalConfirmPasswordToggle = document.getElementById('modal-confirm-password-toggle');
    const modalConfirmPasswordGroup = document.getElementById('modal-confirm-password-group');
    const modalInputGroup = document.getElementById('modal-input-group');

    const confirmLogoutYes = document.getElementById('confirm-logout-yes');
    const confirmLogoutNo = document.getElementById('confirm-logout-no');

    let currentField = ''; // To keep track of which field is being edited

    // Function to open the edit modal
    function openEditModal(title, currentValue, fieldType) {
        modalTitle.textContent = title;
        modalInput.value = currentValue;
        modalInput.style.display = 'block';
        modalFileInput.style.display = 'none';
        modalConfirmPasswordGroup.style.display = 'none';
        modalInputPasswordToggle.style.display = 'none';
        currentField = fieldType;

        if (fieldType === 'profilePic') {
            modalInputGroup.style.display = 'none';
            modalFileInput.style.display = 'block';
        } else if (fieldType === 'password') {
            modalInputGroup.style.display = 'flex';
            modalInput.type = 'password';
            modalInputPasswordToggle.style.display = 'inline-block';
            modalInputPasswordToggle.querySelector('img').src = '../imgs/Eye.png'; // Initial icon for password
            
            modalConfirmPasswordGroup.style.display = 'flex';
            modalConfirmInput.type = 'password';
            modalConfirmInput.value = ''; // Clear confirm password field
            modalConfirmPasswordToggle.style.display = 'inline-block';
            modalConfirmPasswordToggle.querySelector('img').src = '../imgs/Eye.png'; // Initial icon for password
        } else {
            modalInputGroup.style.display = 'flex';
            modalInput.type = 'text';
        }
        editModal.classList.add('show');
    }

    // Event listeners for edit icons
    editNameIcon.addEventListener('click', () => {
        const currentName = document.querySelector('.profile-name').textContent;
        openEditModal('Edit Name', currentName, 'name');
    });


    editPasswordIcon.addEventListener('click', () => {
        const currentPassword = document.getElementById('password').value;
        openEditModal('Edit Password', currentPassword, 'password');
    });

    editProfilePicIcon.addEventListener('click', () => {
        openEditModal('Upload Profile Picture', '', 'profilePic');
    });

    // Password toggle functionality for modal inputs
    modalInputPasswordToggle.addEventListener('click', () => {
        const isPassword = modalInput.type === 'password';
        modalInput.type = isPassword ? 'text' : 'password';
        modalConfirmInput.type = isPassword ? 'text' : 'password'; // Apply to confirm input as well
        modalInputPasswordToggle.querySelector('img').src = isPassword ? '../imgs/Hide.png' : '../imgs/Eye.png';
        modalConfirmPasswordToggle.querySelector('img').src = isPassword ? '../imgs/Hide.png' : '../imgs/Eye.png'; // Apply to confirm icon as well
    });

    // Remove the separate event listener for modalConfirmPasswordToggle as it's now handled by modalInputPasswordToggle

    // Event listener for update button in edit modal
    modalUpdateButton.addEventListener('click', async () => {
        let newValue;
        if (currentField === 'profilePic') {
            const file = modalFileInput.files[0];
            if (file) {
                // Check file size (max 1MB)
                const maxSize = 1 * 1024 * 1024; // 1 MB in bytes
                if (file.size > maxSize) {
                    alert('Image size exceeds the maximum limit of 1MB.');
                    return;
                }

                const reader = new FileReader();
                reader.onloadend = async () => {
                    newValue = reader.result; // Base64 string
                    
                    try {
                        const response = await fetch('../api/update_profile.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({ field: currentField, newValue: newValue })
                        });
                        const data = await response.json();

                        if (data.success) {
                            alert(data.message);
                            editModal.classList.remove('show');
                            // Update the displayed profile picture
                            const profilePictureLarge = document.querySelector('.profile-picture-large');
                            const imgElement = profilePictureLarge.querySelector('img');
                            if (imgElement) {
                                imgElement.src = newValue;
                            } else {
                                // If there's no img tag, create one
                                profilePictureLarge.innerHTML = `<img src="${newValue}" alt="Profile Picture" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">`;
                            }
                        } else {
                            alert('Error: ' + data.message);
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while updating the profile picture.');
                    }
                };
                reader.readAsDataURL(file);
            } else {
                alert('Please select an image to upload.');
            }
        } else {
            newValue = modalInput.value;
            if (currentField === 'name' && newValue.trim() === '') {
                alert('Name cannot be empty.');
                return;
            }
            if (currentField === 'password') {
                const confirmValue = modalConfirmInput.value;
                if (newValue !== confirmValue) {
                    alert('Passwords do not match.');
                    return;
                }
                if (newValue.length < 6) {
                    alert('Password must be at least 6 characters long.');
                    return;
                }
            }

            fetch('../api/update_profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ field: currentField, newValue: newValue })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    editModal.classList.remove('show');
                    // Update the displayed value on the page
                    if (currentField === 'name') {
                        document.querySelector('.profile-name').textContent = newValue;
                    } else if (currentField === 'email') {
                        document.getElementById('email').value = newValue;
                    } else if (currentField === 'password') {
                        document.getElementById('password').value = '************'; // Mask password
                    }
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating.');
            });
        }
    });

    // Event listener for logout button
    logoutButton.addEventListener('click', () => {
        logoutConfirmModal.classList.add('show');
    });

    // Event listeners for logout confirmation
    confirmLogoutYes.addEventListener('click', () => {
        fetch('../api/logout.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                window.location.href = 'login.php';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred during logout.');
        });
    });

    confirmLogoutNo.addEventListener('click', () => {
        logoutConfirmModal.style.display = 'none';
    });

    // Close modals when clicking on the close button or outside the modal
    closeModalButtons.forEach(button => {
        button.addEventListener('click', (event) => {
            event.target.closest('.modal').classList.remove('show');
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target === editModal) {
            editModal.classList.remove('show');
        }
        if (event.target === logoutConfirmModal) {
            logoutConfirmModal.classList.remove('show');
        }
    });
});