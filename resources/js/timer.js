let activeTimer = null;
let timerInterval = null;
let startTime = null;

function formatDuration(hours) {
    const totalSeconds = Math.floor(hours * 3600);
    const h = Math.floor(totalSeconds / 3600);
    const m = Math.floor((totalSeconds % 3600) / 60);
    const s = totalSeconds % 60;
    return `${h}h ${m}min ${s}s`.replace('0h ', '');
}

function updateTimerDisplay() {
    if (!startTime) return;
    
    const timerDisplay = document.getElementById('timer-duration');
    if (!timerDisplay) {
        console.error('Timer display element not found');
        return;
    }
    
    const now = new Date();
    // Arvutame vahe sekundites, ei luba miinust
    const diffSeconds = Math.max(0, Math.floor((now - startTime) / 1000));
    const hours = Math.floor(diffSeconds / 3600);
    const minutes = Math.floor((diffSeconds % 3600) / 60);
    const seconds = diffSeconds % 60;
    
    // Vormindame aja
    let timeStr = '';
    if (hours > 0) timeStr += `${hours}h `;
    if (minutes > 0 || hours > 0) timeStr += `${minutes}min `;
    timeStr += `${seconds}s`;
    
    timerDisplay.textContent = timeStr;
    timerDisplay.classList.remove('hidden');
}

function showTimer(taskTitle) {
    const timerElement = document.getElementById('active-timer');
    if (!timerElement) {
        console.error('Timer element not found');
        return;
    }
    const titleElement = document.getElementById('timer-task-title');
    if (!titleElement) {
        console.error('Timer title element not found');
        return;
    }
    titleElement.textContent = taskTitle;
    timerElement.classList.remove('hidden');
    timerElement.classList.add('flex');
    if (!startTime) {
        startTime = new Date();
    }
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    timerInterval = setInterval(updateTimerDisplay, 1000);
    updateTimerDisplay(); // Update immediately
}

function hideTimer() {
    const timerElement = document.getElementById('active-timer');
    timerElement.classList.add('hidden');
    timerElement.classList.remove('flex');
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    startTime = null;
}

function startTimer(taskId) {
    // Kohe näita taimerit
    const timerElement = document.getElementById('active-timer');
    const titleElement = document.getElementById('timer-task-title');
    if (timerElement && titleElement) {
        timerElement.classList.remove('hidden');
        timerElement.classList.add('flex');
        // Seame algusaja hetke ajaks
        startTime = new Date();
        
        if (timerInterval) {
            clearInterval(timerInterval);
        }
        timerInterval = setInterval(updateTimerDisplay, 1000);
        updateTimerDisplay();
    }

    // Siis tee serveri päring
    fetch(`/time-entries/start/${taskId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            activeTimer = {
                id: data.task.id,
                title: data.task.title
            };
            if (titleElement) {
                titleElement.textContent = data.task.title;
            }
        } else {
            // Kui server tagastab vea, peida taimer
            hideTimer();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        hideTimer();
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function stopTimer(timeEntryId) {
    fetch(`/time-entries/stop/${timeEntryId}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideTimer();
            activeTimer = null;
            startTime = null;
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
        }
    });
}

// Check for active timer on page load
function checkActiveTimer() {
    fetch('/time-entries/current')
        .then(response => response.json())
        .then(data => {
            if (data.active_timer) {
                activeTimer = {
                    id: data.active_timer.id,
                    title: data.active_timer.task_title
                };
                // Parse ISO 8601 kuupäev otse, ilma käsitsi nihketa
                startTime = new Date(data.active_timer.start_time);
                
                const timerElement = document.getElementById('active-timer');
                if (timerElement) {
                    timerElement.classList.remove('hidden');
                    timerElement.classList.add('flex');
                    document.getElementById('timer-task-title').textContent = data.active_timer.task_title;
                    if (timerInterval) {
                        clearInterval(timerInterval);
                    }
                    timerInterval = setInterval(updateTimerDisplay, 1000);
                    updateTimerDisplay();
                }
            }
        });
}

document.addEventListener('DOMContentLoaded', () => {
    checkActiveTimer();

    // Add event listener for stop button
    document.getElementById('stop-timer').addEventListener('click', () => {
        if (activeTimer) {
            stopTimer(activeTimer.id);
        }
    });
});
