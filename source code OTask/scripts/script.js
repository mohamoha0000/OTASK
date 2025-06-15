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
            const projectName = this.querySelector('.hidden-project-name')?.dataset.projectName || '';

            document.getElementById('editTaskId').value = taskId;
            document.getElementById('editTaskTitle').value = taskTitle;
            document.getElementById('editTaskDescription').value = taskDescription;
            document.getElementById('editTaskPriority').value = taskPriority;
            document.getElementById('editStartDate').value = taskStartDate;
            document.getElementById('editDueDate').value = taskEndDate;
            document.getElementById('editDeliverableLink').value = taskDeliverableLink;
            document.getElementById('editTaskStatus').value = taskStatus;
            document.getElementById('editAssignedTo').value = taskAssignedUserId; // Set assigned user

            const editTaskTitleField = document.getElementById('editTaskTitle');
            const editTaskDescriptionField = document.getElementById('editTaskDescription');
            const editTaskPriorityField = document.getElementById('editTaskPriority');
            const editStartDateField = document.getElementById('editStartDate');
            const editDueDateField = document.getElementById('editDueDate');
            const editTaskStatusField = document.getElementById('editTaskStatus');
            const editDeliverableLinkField = document.getElementById('editDeliverableLink');
            const editAssignedToField = document.getElementById('editAssignedTo');
            const assignedToGroup = document.getElementById('assignedToGroup');
            const editProjectInfoDiv = document.getElementById('editProjectInfo');
            const editProjectNameSpan = document.getElementById('editProjectName');
            const editProjectLink = document.getElementById('editProjectLink');

            // Reset fields to enabled first
            editTaskTitleField.disabled = false;
            editTaskDescriptionField.disabled = false;
            editTaskPriorityField.disabled = false;
            editStartDateField.disabled = false;
            editDueDateField.disabled = false;
            editDeliverableLinkField.disabled = false;
            editTaskStatusField.disabled = false;
            editAssignedToField.disabled = false;
            assignedToGroup.style.display = 'block'; // Show by default

            // Hide project info by default
            editProjectInfoDiv.style.display = 'none';

            // Determine if the current user is the assigned user for this task
            const isAssignedUser = (taskAssignedUserId == currentUserId);

            // Permissions logic
            let canEditAllFields = false;
            let canEditStatus = false;
            let canMarkCompleted = false;
            let canChangeAssignedUser = false;

            // If it's a personal task or the current user is the assigned user
            if (!taskProjectId || isAssignedUser) {
                canEditAllFields = true;
                canEditStatus = true;
                canMarkCompleted = true;
                canChangeAssignedUser = true;
            }

            // If it's a project task
            if (taskProjectId) {
                editProjectInfoDiv.style.display = 'block';
                editProjectNameSpan.textContent = projectName;
                editProjectLink.href = `projects.php?id=${taskProjectId}`;

                if (userRole === 'admin' || userRole === 'supervisor') { // Assuming 'supervisor' is the role for project admins
                    canEditAllFields = true;
                    canEditStatus = true;
                    canMarkCompleted = true;
                    canChangeAssignedUser = true;
                } else if (userRole === 'member') { // Normal project member
                    canEditAllFields = false;
                    canEditStatus = true;
                    canChangeAssignedUser = false;
                    // A normal member cannot mark a project task as completed
                    canMarkCompleted = false;
                }
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
                editAssignedToField.disabled = true;
                assignedToGroup.style.display = 'none'; // Hide if not allowed to change
            }

            // Disable 'completed' option if not allowed
            const completedOption = editTaskStatusField.querySelector('option[value="completed"]');
            if (completedOption) {
                completedOption.disabled = !canMarkCompleted;
            }

            editTaskModal.classList.add('show');
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
};
