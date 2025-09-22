<?php
session_start();

// Initialize to-do list if not set
if (!isset($_SESSION['todos'])) {
    $_SESSION['todos'] = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (!empty($_POST['task'])) {
                    $priority = in_array($_POST['priority'], ['Low', 'Medium', 'High']) ? $_POST['priority'] : 'Low';
                    $_SESSION['todos'][] = [
                        'text' => htmlspecialchars($_POST['task']),
                        'completed' => false,
                        'priority' => $priority,
                        'completion_date' => null
                    ];
                    $_SESSION['alert'] = 'Task added successfully!';
                }
                break;
            case 'done':
                $index = (int)$_POST['done'];
                if (isset($_SESSION['todos'][$index])) {
                    $_SESSION['todos'][$index]['completed'] = !$_SESSION['todos'][$index]['completed'];
                    $_SESSION['todos'][$index]['completion_date'] = $_SESSION['todos'][$index]['completed'] ? date('Y-m-d H:i:s') : null;
                }
                break;
            case 'delete':
                $index = (int)$_POST['delete'];
                if (isset($_SESSION['todos'][$index])) {
                    unset($_SESSION['todos'][$index]);
                    $_SESSION['todos'] = array_values($_SESSION['todos']);
                    $_SESSION['alert'] = 'Task deleted successfully!';
                }
                break;
            case 'clear':
                $_SESSION['todos'] = [];
                $_SESSION['alert'] = 'All tasks cleared!';
                break;
            case 'clear_completed':
                $_SESSION['todos'] = array_values(array_filter($_SESSION['todos'], function($todo) {
                    return !$todo['completed'];
                }));
                $_SESSION['alert'] = 'Completed tasks cleared!';
                break;
            case 'edit':
                if (isset($_POST['index']) && !empty($_POST['task']) && isset($_SESSION['todos'][$_POST['index']])) {
                    $priority = in_array($_POST['priority'], ['Low', 'Medium', 'High']) ? $_POST['priority'] : 'Low';
                    $_SESSION['todos'][$_POST['index']]['text'] = htmlspecialchars($_POST['task']);
                    $_SESSION['todos'][$_POST['index']]['priority'] = $priority;
                    $_SESSION['alert'] = 'Task updated successfully!';
                }
                break;
        }
    }
    header('Location: index.php');
    exit;
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'all';
$tasks = $_SESSION['todos'];

if ($sort === 'completed') {
    $tasks = array_filter($tasks, function($todo) { return $todo['completed']; });
} elseif ($sort === 'pending') {
    $tasks = array_filter($tasks, function($todo) { return !$todo['completed']; });
} elseif ($sort === 'priority') {
    usort($tasks, function($a, $b) {
        $priorityOrder = ['High' => 3, 'Medium' => 2, 'Low' => 1];
        return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>To-Do List</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: linear-gradient(to bottom, #0D9488, #BAE6FD);
            color: #333;
            min-height: 100vh;
        }
        h1 { text-align: center; color: #1F2937; }
        .container {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .todo-form {
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 60%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        input[type="text"]:focus {
            border-color: #0D9488;
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.2);
            outline: none;
        }
        select {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            background: #fff;
        }
        button {
            padding: 10px 20px;
            background-color: #0D9488;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        button:hover {
            background-color: #0F766E;
            transform: translateY(-1px);
        }
        .todo-list {
            list-style: none;
            padding: 0;
        }
        .todo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            border-radius: 8px;
            transition: transform 0.3s, opacity 0.3s;
            animation: fadeIn 0.5s ease-in;
        }
        .todo-item:nth-child(odd) {
            background: #F9FAFB;
        }
        .todo-item:nth-child(even) {
            background: #FFFFFF;
        }
        .todo-item.completed {
            text-decoration: line-through;
            color: #6B7280;
            background: #E5E7EB;
        }
        .todo-item button.delete-btn {
            background-color: #EF4444;
        }
        .todo-item button.delete-btn:hover {
            background-color: #DC2626;
        }
        .todo-item button.edit-btn {
            background-color: #6B7280;
        }
        .todo-item button.edit-btn:hover {
            background-color: #4B5563;
        }
        .priority-low { background-color: #9CA3AF; color: white; padding: 3px 10px; border-radius: 6px; }
        .priority-medium { background-color: #F59E0B; color: white; padding: 3px 10px; border-radius: 6px; }
        .priority-high { background-color: #EF4444; color: white; padding: 3px 10px; border-radius: 6px; }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: #fff;
            padding: 24px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            max-width: 400px;
            width: 90%;
        }
        .modal-content p {
            margin: 0 0 20px;
            color: #1F2937;
            font-weight: 500;
        }
        .modal-content button {
            margin: 0 10px;
        }
        .modal-content .cancel-btn {
            background-color: #6B7280;
        }
        .modal-content .cancel-btn:hover {
            background-color: #4B5563;
        }
        .modal-content .confirm-btn {
            background-color: #EF4444;
        }
        .modal-content .confirm-btn:hover {
            background-color: #DC2626;
        }
        .modal-content .edit-confirm-btn {
            background-color: #0D9488;
        }
        .modal-content .edit-confirm-btn:hover {
            background-color: #0F766E;
        }
        .clear-completed-btn {
            background-color: #4B5563;
            margin-top: 10px;
        }
        .clear-completed-btn:hover {
            background-color: #374151;
        }
        .clear-all-btn {
            background-color: #4B5563;
            margin-top: 10px;
        }
        .clear-all-btn:hover {
            background-color: #374151;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 600px) {
            input[type="text"], select { width: 100%; }
            .todo-item { flex-direction: column; text-align: center; }
            .todo-item button { margin: 5px 0 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>To-Do List</h1>
        <div class="todo-form">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <input type="text" name="task" placeholder="Enter a new task" required>
                <select name="priority">
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
                <button type="submit">Add Task</button>
            </form>
        </div>
        <!-- Task Count -->
        <p class="text-sm text-gray-600 mb-4">
            Total: <?php echo count($_SESSION['todos']); ?> |
            Completed: <?php echo count(array_filter($_SESSION['todos'], function($todo) { return $todo['completed']; })); ?> |
            Pending: <?php echo count(array_filter($_SESSION['todos'], function($todo) { return !$todo['completed']; })); ?>
        </p>
        <!-- Sort Options -->
        <form method="GET" class="mb-4">
            <select name="sort" onchange="this.form.submit()">
                <option value="all" <?php echo $sort === 'all' ? 'selected' : ''; ?>>All Tasks</option>
                <option value="completed" <?php echo $sort === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="pending" <?php echo $sort === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="priority" <?php echo $sort === 'priority' ? 'selected' : ''; ?>>By Priority</option>
            </select>
        </form>
        <ul class="todo-list">
            <?php if (empty($tasks)): ?>
                <li class="p-3 bg-white rounded text-gray-800">No tasks yet!</li>
            <?php else: ?>
                <?php foreach ($tasks as $index => $todo): ?>
                    <li class="todo-item <?php echo $todo['completed'] ? 'completed' : ''; ?>">
                        <div>
                            <span><?php echo htmlspecialchars($todo['text']); ?></span>
                            <span class="priority-<?php echo strtolower($todo['priority']); ?>">
                                <?php echo $todo['priority']; ?>
                            </span>
                            <?php if ($todo['completed'] && $todo['completion_date']): ?>
                                <p class="text-xs text-gray-500">Completed: <?php echo $todo['completion_date']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="done">
                                <input type="hidden" name="done" value="<?php echo $index; ?>">
                                <button type="submit"><?php echo $todo['completed'] ? 'Undo' : 'Done'; ?></button>
                            </form>
                            <button type="button" class="edit-btn" onclick="showEditModal(<?php echo $index; ?>, '<?php echo htmlspecialchars(addslashes($todo['text'])); ?>', '<?php echo $todo['priority']; ?>')">Edit</button>
                            <button type="button" class="delete-btn" onclick="showDeleteModal(<?php echo $index; ?>, '<?php echo htmlspecialchars(addslashes($todo['text'])); ?>')">Delete</button>
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php endif; ?>
        </ul>
        <!-- Clear All Tasks -->
        <?php if (count($_SESSION['todos']) > 0): ?>
            <form method="POST" id="clearForm" class="mt-4">
                <input type="hidden" name="action" value="clear">
                <button type="button" class="clear-all-btn" onclick="showClearModal()">Clear All Tasks</button>
            </form>
        <?php endif; ?>
        <!-- Clear Completed Tasks -->
        <?php if (count(array_filter($_SESSION['todos'], function($todo) { return $todo['completed']; })) > 0): ?>
            <form method="POST" class="mt-2">
                <input type="hidden" name="action" value="clear_completed">
                <button type="button" class="clear-completed-btn" onclick="showClearCompletedModal()">Clear Completed Tasks</button>
            </form>
        <?php endif; ?>
    </div>
    
    <!-- Delete Task Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to delete "<span id="taskText"></span>"?</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="delete" id="deleteIndex">
                <button type="submit" class="confirm-btn">Yes, Delete</button>
                <button type="button" class="cancel-btn" onclick="closeModal('deleteModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Clear All Tasks Modal -->
    <div id="clearModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to clear all tasks?</p>
            <form method="POST">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="confirm-btn">Yes, Clear All</button>
                <button type="button" class="cancel-btn" onclick="closeModal('clearModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Clear Completed Tasks Modal -->
    <div id="clearCompletedModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to clear all completed tasks?</p>
            <form method="POST">
                <input type="hidden" name="action" value="clear_completed">
                <button type="submit" class="confirm-btn">Yes, Clear Completed</button>
                <button type="button" class="cancel-btn" onclick="closeModal('clearCompletedModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <p>Edit Task</p>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="index" id="editIndex">
                <input type="text" name="task" id="editTaskText" class="w-full p-2 border rounded mb-4" required>
                <select name="priority" id="editPriority" class="w-full p-2 border rounded mb-4">
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
                <button type="submit" class="edit-confirm-btn">Save</button>
                <button type="button" class="cancel-btn" onclick="closeModal('editModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        // Show alert if set
        <?php if (isset($_SESSION['alert'])): ?>
            alert(<?php echo json_encode($_SESSION['alert']); ?>);
            <?php unset($_SESSION['alert']); ?>
        <?php endif; ?>

        function showDeleteModal(index, taskText) {
            document.getElementById('deleteIndex').value = index;
            document.getElementById('taskText').textContent = taskText;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function showClearModal() {
            document.getElementById('clearModal').style.display = 'flex';
        }

        function showClearCompletedModal() {
            document.getElementById('clearCompletedModal').style.display = 'flex';
        }

        function showEditModal(index, taskText, priority) {
            document.getElementById('editIndex').value = index;
            document.getElementById('editTaskText').value = taskText;
            document.getElementById('editPriority').value = priority;
            document.getElementById('editModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['deleteModal', 'clearModal', 'clearCompletedModal', 'editModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    closeModal(modalId);
                }
            });
        }
    </script>
</body>
</html>