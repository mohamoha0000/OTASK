function showTextEffect(select,speed) {
    let div = document.querySelector(select);

    let all_h2 = div.querySelectorAll("h2");
    let temph2=[];
    all_h2.forEach(element => {
        temph2.push(element.textContent)
        element.textContent="";
    });


    let indexh2=0;
    let index=0;
    let play=true;
    const intervalId = setInterval(() => {
        if (play){
            if (index>temph2[indexh2].length-1){
                indexh2+=1
                index=0
            }
            if(indexh2>all_h2.length-1){
                play=false;
                setTimeout(() => {
                    clearInterval(intervalId);
                    showTextEffect(select, speed);
                }, 3000);
            }else{
                all_h2[indexh2].textContent+=temph2[indexh2][index];
                index+=1;
            }
        } 
    }, speed);
}
window.onload = function() {
    const mobileMenu = document.getElementById('mobile-menu');
    const navLinks = document.querySelector('.nav-links');

    if (mobileMenu && navLinks) {
        mobileMenu.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }

    // Close the mobile menu when a link is clicked
    navLinks.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            navLinks.classList.remove('active');
        });
    });

    // Close the mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!navLinks.contains(event.target) && !mobileMenu.contains(event.target)) {
            navLinks.classList.remove('active');
        }
    });

    const newTaskButton = document.getElementById('newTaskBtn');
    const newTaskModal = document.getElementById('newTaskModal');
    const closeButtons = document.querySelectorAll('.close-button');

    if (newTaskButton && newTaskModal) {
        newTaskButton.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            newTaskModal.classList.add('show');
        });
    }

    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (newTaskModal) { // Add null check
                newTaskModal.classList.remove('show');
            }
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (newTaskModal && event.target == newTaskModal) { // Add null check
            newTaskModal.classList.remove('show');
        }
    });

    // New Project Modal functionality (for projects.php)
    const newProjectBtn = document.getElementById('newProjectBtn');
    const newProjectModal = document.getElementById('newProjectModal');
    // Re-use closeButtons for newProjectModal as they share the same class
    const newProjectCloseButtons = newProjectModal ? newProjectModal.querySelectorAll('.close-button') : [];

    if (newProjectBtn && newProjectModal) {
        newProjectBtn.addEventListener('click', function(event) {
            event.preventDefault();
            newProjectModal.classList.add('show');
        });
    }

    newProjectCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            newProjectModal.classList.remove('show');
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == newProjectModal) {
            newProjectModal.classList.remove('show');
        }
    });

    const editTaskButtons = document.querySelectorAll('.edit-task-btn');
    const editTaskModal = document.getElementById('editTaskModal');
    const editCloseButtons = document.querySelectorAll('.edit-close-button');

    editTaskButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            const taskId = this.dataset.taskId;
            const taskTitle = this.dataset.taskTitle;
            const taskDescription = this.dataset.taskDescription;
            const taskPriority = this.dataset.taskPriority;
            const taskStartDate = this.dataset.taskStartDate;
            const taskEndDate = this.dataset.taskEndDate;
            const taskStatus = this.dataset.taskStatus;
            const taskProjectId = this.dataset.taskProjectId;
            const taskDeliverableLink = this.dataset.taskDeliverableLink;
            const taskAssignedUserId = this.dataset.taskAssignedUserId;
            const userRole = this.dataset.userRole; // Get user role from data attribute
            const isProjectSupervisorData = this.dataset.isProjectSupervisor === 'true';
            const isProjectMemberData = this.dataset.isProjectMember === 'true';
            const isPersonalTask = this.dataset.isPersonalTask === 'true';
            const projectName = this.querySelector('.hidden-project-name')?.dataset.projectName || '';

            document.getElementById('editTaskId').value = taskId;
            document.getElementById('editTaskTitle').value = taskTitle;
            document.getElementById('editTaskDescription').value = taskDescription;
            document.getElementById('editTaskPriority').value = taskPriority;
            document.getElementById('editStartDate').value = taskStartDate;
            document.getElementById('editDueDate').value = taskEndDate;
            document.getElementById('editDeliverableLink').value = taskDeliverableLink;
            document.getElementById('editTaskStatus').value = taskStatus;
            document.getElementById('editAssignedUser').value = taskAssignedUserId; // Set assigned user

            const editTaskTitleField = document.getElementById('editTaskTitle');
            const editTaskDescriptionField = document.getElementById('editTaskDescription');
            const editTaskPriorityField = document.getElementById('editTaskPriority');
            const editStartDateField = document.getElementById('editStartDate');
            const editDueDateField = document.getElementById('editDueDate');
            const editTaskStatusField = document.getElementById('editTaskStatus');
            const editDeliverableLinkField = document.getElementById('editDeliverableLink');
            const editAssignedUserField = document.getElementById('editAssignedUser'); // Get assigned user select
            const editProjectInfoDiv = document.getElementById('editProjectInfo');
            const editProjectNameSpan = document.getElementById('editProjectName');
            const editProjectLink = document.getElementById('editProjectLink');
            // No delete button in view_project.php, so remove these
            // const deleteTaskBtn = document.getElementById('deleteTaskBtn');
            // const deleteTaskForm = document.getElementById('deleteTaskForm');
            // const deleteTaskIdInput = document.getElementById('deleteTaskId');

            // Reset fields to enabled first
            editTaskTitleField.disabled = false;
            editTaskDescriptionField.disabled = false;
            editTaskPriorityField.disabled = false;
            editStartDateField.disabled = false;
            editDueDateField.disabled = false;
            editDeliverableLinkField.disabled = false;
            editTaskStatusField.disabled = false;
            editAssignedUserField.disabled = false;

            // Hide project info by default
            editProjectInfoDiv.style.display = 'none';

            // Determine if the current user is the assigned user for this task
            const isAssignedUser = (taskAssignedUserId == currentUserId);
            const isCurrentUserSupervisor = (userRole === 'supervisor'); // Assuming 'supervisor' is the role for admin

            // Permissions logic
            let canEditAllFields = false;
            let canEditStatus = false;
            let canMarkCompleted = false;
            let canChangeAssignedUser = false;

            // Determine if it's a project task
            const isProjectTask = (taskProjectId !== '');

            // Determine if the current user is a non-supervisor project member
            const isNonSupervisorProjectMember = isProjectTask && isProjectMemberData && !isProjectSupervisorData;

            // Permissions logic
            if (isProjectTask) {
                // Logic for Project Tasks
                if (isProjectSupervisorData) {
                    // Project Supervisor: Full permissions
                    canEditAllFields = true;
                    canEditStatus = true;
                    canMarkCompleted = true;
                    canChangeAssignedUser = true;
                } else if (isProjectMemberData) { // If user is a project member (not supervisor)
                    canEditAllFields = false; // Cannot edit all fields
                    canEditStatus = true; // Can edit status
                    canChangeAssignedUser = false; // Cannot change assigned user
                    // A project member can mark their own assigned task as completed
                    canMarkCompleted = false; // Non-supervisor project members cannot mark tasks as completed
                } else {
                    // Project Task, but user is not supervisor and not a member
                    // No permissions to edit
                    canEditAllFields = false;
                    canEditStatus = false;
                    canMarkCompleted = false;
                    canChangeAssignedUser = false;
                }
            } else {
                // This section is for personal tasks. In view_project.php, all tasks are project tasks.
                // This block should ideally not be reached if the logic is correct for view_project.php.
                // However, keeping it for robustness if task data changes.
                if (isAssignedUser) {
                    canEditAllFields = true;
                    canEditStatus = true;
                    canMarkCompleted = true;
                    canChangeAssignedUser = true;
                } else {
                    canEditAllFields = false;
                    canEditStatus = false;
                    canMarkCompleted = false;
                    canChangeAssignedUser = false;
                }
            }

            // If it's a project task, display project info
            if (isProjectTask) {
                editProjectInfoDiv.style.display = 'block';
                editProjectNameSpan.textContent = projectName;
                editProjectLink.href = `projects.php?id=${taskProjectId}`;
            }

            // Apply client-side disabling based on determined permissions
            if (!canEditAllFields) {
                editTaskTitleField.disabled = true;
                editTaskDescriptionField.disabled = true;
                editTaskPriorityField.disabled = true;
                editStartDateField.disabled = true;
                editDueDateField.disabled = true;
            }

            if (!canEditStatus) {
                editTaskStatusField.disabled = true;
            }

            if (!canChangeAssignedUser) {
                editAssignedUserField.disabled = true;
            }

            // Disable 'completed' option if not allowed
            const completedOption = editTaskStatusField.querySelector('option[value="completed"]');
            if (completedOption) {
                completedOption.disabled = !canMarkCompleted;
            }

            editTaskModal.classList.add('show');

            // No delete button in view_project.php, so no logic needed here.
        });
    });

    editCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            editTaskModal.classList.remove('show');
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == editTaskModal) {
            editTaskModal.classList.remove('show');
        }
    });

    // Settings Modal functionality
    const settingsIconHeader = document.querySelector('.settings-icon-header');
    const settingsIconModal = document.querySelector('.settings-icon-modal');
    const settingsModal = document.getElementById('settingsModal');
    const settingsCloseButtons = document.querySelectorAll('.settings-close-button');

    function openSettingsModal() {
        if (projectMenuModal && projectMenuModal.classList.contains('show')) {
            projectMenuModal.classList.remove('show'); // Close project menu modal if it's open
        }
        settingsModal.classList.add('show');
    }

    if (settingsIconHeader) {
        settingsIconHeader.addEventListener('click', openSettingsModal);
    }
    if (settingsIconModal) {
        settingsIconModal.addEventListener('click', openSettingsModal);
    }

    settingsCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            settingsModal.classList.remove('show');
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == settingsModal) {
            settingsModal.classList.remove('show');
        }
    });

    // Project Menu Modal functionality
    const projectMenuToggle = document.getElementById('project-menu-toggle');
    const projectMenuModal = document.getElementById('projectMenuModal');
    const projectMenuCloseButtons = document.querySelectorAll('.project-menu-close-button');
    const newTaskBtnModal = document.getElementById('newTaskBtnModal');
    const newMemberBtnModal = document.getElementById('newMemberBtnModal');

    if (projectMenuToggle && projectMenuModal) {
        projectMenuToggle.addEventListener('click', function() {
            projectMenuModal.classList.add('show');
        });
    }

    projectMenuCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            projectMenuModal.classList.remove('show');
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == projectMenuModal) {
            projectMenuModal.classList.remove('show');
        }
    });

    // Handle buttons inside Project Menu Modal
    if (newTaskBtnModal) {
        newTaskBtnModal.addEventListener('click', function(event) {
            event.preventDefault();
            projectMenuModal.classList.remove('show'); // Close project menu modal
            newTaskModal.classList.add('show'); // Open new task modal
        });
    }

    if (newMemberBtnModal) {
        newMemberBtnModal.addEventListener('click', function(event) {
            event.preventDefault();
            projectMenuModal.classList.remove('show'); // Close project menu modal
            inviteMemberModal.classList.add('show'); // Open invite member modal
        });
    }

    // Chat Icon functionality
    const chatIconHeader = document.querySelector('.chat-icon-header');
    const chatIconModal = document.querySelector('.chat-icon-modal');
    
    const projectChatModal = document.getElementById('projectChatModal');

    if ( chatIconHeader) {
        chatIconHeader.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            projectChatModal.classList.add('show');
            try {
                fristopenchat = true;
            } catch (error) {
                
            }
            if (projectMenuModal) {
                projectMenuModal.classList.remove('show'); // Close project menu modal if opened from there
            }
        });
    }
    
    if (chatIconModal) {
        chatIconModal.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            projectChatModal.classList.add('show');
            try {
                fristopenchat = true;
            } catch (error) {
                
            }
            if (projectMenuModal) {
                projectMenuModal.classList.remove('show'); // Close project menu modal if opened from there
            }
        });
    }

    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            if (projectChatModal) { // Add null check
                projectChatModal.classList.remove('show');
            }
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (projectChatModal && event.target == projectChatModal) { // Add null check
            projectChatModal.classList.remove('show');
        }
    });

    // Invite Member Modal functionality (original)
    const inviteMemberModal = document.getElementById('inviteMemberModal');
    const inviteMemberCloseButtons = document.querySelectorAll('.invite-member-close-button');
    const newTaskBtn = document.getElementById('newTaskBtn'); // Get the original New Task button
    const newMemberBtn = document.getElementById('newMemberBtn'); // Get the original New Member button

    // Event listeners for original New Task and New Member buttons (for large screens)
    if (newTaskBtn && newTaskModal) {
        newTaskBtn.addEventListener('click', function(event) {
            event.preventDefault();
            newTaskModal.classList.add('show');
        });
    }

    if (newMemberBtn && inviteMemberModal) {
        newMemberBtn.addEventListener('click', function(event) {
            event.preventDefault();
            inviteMemberModal.classList.add('show');
        });
    }

    inviteMemberCloseButtons.forEach(button => {
        button.addEventListener('click', function() {
            inviteMemberModal.classList.remove('show');
        });
    });

    window.addEventListener('click', function(event) {
        if (event.target == inviteMemberModal) {
            inviteMemberModal.classList.remove('show');
        }
    });

    // Copy deliverable link functionality
    const copyDeliverableLinkBtn = document.getElementById('copyDeliverableLink');
    if (copyDeliverableLinkBtn) {
        copyDeliverableLinkBtn.addEventListener('click', function() {
            const deliverableLinkInput = document.getElementById('editDeliverableLink');
            if (deliverableLinkInput && deliverableLinkInput.value) {
                navigator.clipboard.writeText(deliverableLinkInput.value).then(() => {
                    alert('Deliverable link copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy text: ', err);
                    alert('Failed to copy link. Please copy manually.');
                });
            } else {
                alert('No deliverable link to copy.');
            }
        });
    }

    // Handle modal display for assign all tasks
    const assignAllTasksModal = document.getElementById('assignAllTasksModal');
    const assignAllTasksCloseButtons = document.querySelectorAll('.assign-all-tasks-close-button');

    if (assignAllTasksModal) {
        assignAllTasksCloseButtons.forEach(button => {
            button.addEventListener('click', () => {
                assignAllTasksModal.classList.remove('show');
                assignAllTasksModal.style.display = 'none';
            });
        });

        window.addEventListener('click', (event) => {
            if (event.target == assignAllTasksModal) {
                assignAllTasksModal.classList.remove('show');
                assignAllTasksModal.style.display = 'none';
            }
        });
    }

    // New functionality for "Assign Unassigned Task" button and modal
    const assignUnassignedTaskBtn = document.getElementById('assignUnassignedTaskBtn');
    const assignUnassignedTaskModal = document.getElementById('assignUnassignedTaskModal');
    const assignUnassignedTaskCloseButtons = document.querySelectorAll('.assign-unassigned-task-close-button');
    const unassignedTasksForAssignmentList = document.getElementById('unassignedTasksForAssignmentList');

    if (assignUnassignedTaskBtn && assignUnassignedTaskModal) {
        assignUnassignedTaskBtn.addEventListener('click', () => {
            assignUnassignedTaskModal.classList.add('show');
            fetchUnassignedTasksForAssignment();
        });
    }

    assignUnassignedTaskCloseButtons.forEach(button => {
        button.addEventListener('click', () => {
            assignUnassignedTaskModal.classList.remove('show');
        });
    });

    window.addEventListener('click', (event) => {
        if (event.target == assignUnassignedTaskModal) {
            assignUnassignedTaskModal.classList.remove('show');
        }
    });

    function fetchUnassignedTasksForAssignment() {
        const projectId = new URLSearchParams(window.location.search).get('project_id');
        fetch(`../api/get_unassigned_tasks.php?project_id=${projectId}`)
            .then(response => response.json())
            .then(result => {
                unassignedTasksForAssignmentList.innerHTML = '';
                if (result.success && result.tasks.length > 0) {
                    result.tasks.forEach(task => {
                        const li = document.createElement('li');
                        li.innerHTML = `
                            <span>${task.title}</span>
                            <select class="assign-member-dropdown" data-task-id="${task.id}">
                                <option value="">Select Member</option>
                                ${projectMembers.map(member => `<option value="${member.id}">${member.name}</option>`).join('')}
                            </select>
                            <button class="btn btn-assign btn-small" onclick="assignTaskToSelectedMember(${task.id})">Assign</button>
                        `;
                        unassignedTasksForAssignmentList.appendChild(li);
                    });

                    // Add event listeners for dropdown changes
                    document.querySelectorAll('.assign-member-dropdown').forEach(dropdown => {
                        dropdown.addEventListener('change', function() {
                            const taskId = this.dataset.taskId;
                            const assignButton = this.nextElementSibling; // The assign button is the next sibling
                            if (this.value) {
                                assignButton.disabled = false;
                            } else {
                                assignButton.disabled = true;
                            }
                        });
                        // Initially disable assign button if no member is selected
                        const assignButton = dropdown.nextElementSibling;
                        assignButton.disabled = true;
                    });

                } else {
                    unassignedTasksForAssignmentList.innerHTML = '<li>No unassigned tasks available.</li>';
                    if (result.message) {
                        console.warn('API message:', result.message);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching unassigned tasks:', error);
                unassignedTasksForAssignmentList.innerHTML = '<li>Error loading tasks.</li>';
            });
    }

    function assignTaskToSelectedMember(taskId) {
        const dropdown = document.querySelector(`.assign-member-dropdown[data-task-id="${taskId}"]`);
        const assignedUserId = dropdown.value;
        const taskTitle = dropdown.previousElementSibling.textContent; // Get task title from the span
        const assignedUserName = dropdown.options[dropdown.selectedIndex].text; // Get selected member name

        if (assignedUserId) {
            if (confirm(`Assign "${taskTitle}" to ${assignedUserName}?`)) {
                const projectId = new URLSearchParams(window.location.search).get('project_id');
                fetch('../api/assign_task_to_member.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        task_id: taskId,
                        user_id: assignedUserId,
                        project_id: projectId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Task "${taskTitle}" assigned to ${assignedUserName} successfully!`);
                        assignUnassignedTaskModal.classList.remove('show');
                        location.reload(); // Reload to update task lists
                    } else {
                        alert('Failed to assign task: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error assigning task:', error);
                    alert('An error occurred while assigning the task.');
                });
            }
        } else {
            alert('Please select a member to assign the task to.');
        }
    }
};
