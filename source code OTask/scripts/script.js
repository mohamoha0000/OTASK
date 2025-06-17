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
            newTaskModal.classList.remove('show');
        });
    });

    // Close modal when clicking outside of it
    window.addEventListener('click', function(event) {
        if (event.target == newTaskModal) {
            newTaskModal.classList.remove('show');
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
            const isPersonalTask = this.dataset.isPersonalTask === 'true'; // New: Get personal task status
            const projectName = this.querySelector('.hidden-project-name')?.dataset.projectName || '';

            document.getElementById('editTaskId').value = taskId;
            document.getElementById('editTaskTitle').value = taskTitle;
            document.getElementById('editTaskDescription').value = taskDescription;
            document.getElementById('editTaskPriority').value = taskPriority;
            document.getElementById('editStartDate').value = taskStartDate;
            document.getElementById('editDueDate').value = taskEndDate;
            document.getElementById('editDeliverableLink').value = taskDeliverableLink;
            document.getElementById('editTaskStatus').value = taskStatus;
            // document.getElementById('editAssignedTo').value = taskAssignedUserId; // Removed as "Assigned To" input is removed

            const editTaskTitleField = document.getElementById('editTaskTitle');
            const editTaskDescriptionField = document.getElementById('editTaskDescription');
            const editTaskPriorityField = document.getElementById('editTaskPriority');
            const editStartDateField = document.getElementById('editStartDate');
            const editDueDateField = document.getElementById('editDueDate');
            const editTaskStatusField = document.getElementById('editTaskStatus');
            const editDeliverableLinkField = document.getElementById('editDeliverableLink');
            // const editAssignedToField = document.getElementById('editAssignedTo'); // Removed as "Assigned To" input is removed
            // const assignedToGroup = document.getElementById('assignedToGroup'); // Removed as "Assigned To" input is removed
            const editProjectInfoDiv = document.getElementById('editProjectInfo');
            const editProjectNameSpan = document.getElementById('editProjectName');
            const editProjectLink = document.getElementById('editProjectLink');
            const deleteTaskBtn = document.getElementById('deleteTaskBtn'); // New: Get delete button
            const deleteTaskForm = document.getElementById('deleteTaskForm'); // New: Get delete form
            const deleteTaskIdInput = document.getElementById('deleteTaskId'); // New: Get hidden task ID for delete form

            // Reset fields to enabled first
            editTaskTitleField.disabled = false;
            editTaskDescriptionField.disabled = false;
            editTaskPriorityField.disabled = false;
            editStartDateField.disabled = false;
            editDueDateField.disabled = false;
            editDeliverableLinkField.disabled = false;
            editTaskStatusField.disabled = false;
            // editAssignedToField.disabled = false; // Removed as "Assigned To" input is removed
            // assignedToGroup.style.display = 'block'; // Removed as "Assigned To" input is removed

            // Hide project info by default
            editProjectInfoDiv.style.display = 'none';

            // Determine if the current user is the assigned user for this task
            const isAssignedUser = (taskAssignedUserId == currentUserId);

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
                } else if (isNonSupervisorProjectMember) {
                    // Non-supervisor Project Member: Restricted permissions
                    canEditAllFields = false; // Cannot edit all fields
                    canEditStatus = true; // Can edit status
                    canChangeAssignedUser = false; // Cannot change assigned user
                    canMarkCompleted = false; // Cannot mark as completed
                } else {
                    // Project Task, but user is not supervisor and not a member
                    // No permissions to edit
                    canEditAllFields = false;
                    canEditStatus = false;
                    canMarkCompleted = false;
                    canChangeAssignedUser = false;
                }
            } else {
                // Logic for Personal Tasks (not associated with a project)
                if (isAssignedUser) {
                    // Assigned User for personal task: Full permissions
                    canEditAllFields = true;
                    canEditStatus = true;
                    canMarkCompleted = true;
                    canChangeAssignedUser = true;
                } else {
                    // Personal Task, but user is not assigned
                    // No permissions to edit
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

            // if (!canChangeAssignedUser) { // Removed as "Assigned To" input is removed
            //     editAssignedToField.disabled = true;
            //     assignedToGroup.style.display = 'none'; // Hide if not allowed to change
            // }

            // Disable 'completed' option if not allowed
            const completedOption = editTaskStatusField.querySelector('option[value="completed"]');
            if (completedOption) {
                completedOption.disabled = !canMarkCompleted;
            }

            editTaskModal.classList.add('show');

            // Show/hide delete button based on task type and permissions
            console.log('Task ID:', taskId);
            console.log('isPersonalTask:', isPersonalTask);
            console.log('isAssignedUser:', isAssignedUser);

            if (deleteTaskBtn) { // Ensure the button element exists
                if (isPersonalTask && isAssignedUser) {
                    deleteTaskBtn.style.display = 'inline-block'; // Show the button
                    deleteTaskBtn.onclick = function() {
                        console.log('Delete button clicked for task ID:', taskId);
                        if (confirm('Are you sure you want to delete this personal task?')) {
                            deleteTaskIdInput.value = taskId;
                            deleteTaskForm.submit();
                        }
                    };
                } else {
                    deleteTaskBtn.style.display = 'none'; // Hide the button
                }
            } else {
                console.error('Delete task button not found!');
            }
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
};
