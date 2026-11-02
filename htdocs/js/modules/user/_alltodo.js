cargohub.user_alltodo = donrey.BASE._extend(function() {
  var self = this,
    todo_data = [],
    selected_todos = {},
//    current_filter = "all",
    current_filter = "active",
    current_priority = "",
    current_search = "",
    todo_load_action = "getTodoList",
    escape_Html = function(value) {
      if (value === null || value === undefined) {
        return "";
      }
      return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    },
    capitalize = function(value) {
      if (!value) {
        return "";
      }
      value = String(value);
      return value.charAt(0).toUpperCase() + value.substring(1);
    },
    normalize_Boolean = function(value) {
      if (value === 1 || value === "1" || value === true || value === "true" || value === "completed") {
        return 1;
      }
      return 0;
    },
    normalize_Todo = function(row) {
      var todo = {};
      row = row || {};
      todo.id = row.id || row.todo_id || 0;
      todo.title = row.title || row.todo_title || "";
      todo.description = row.description || row.todo_description || "";
      todo.remark = row.remark || row.remarks || "";
      todo.priority = String(row.priority || "normal").toLowerCase();
      todo.date = row.due_date || row.date || row.todo_date || row.task_date || "";
      todo.status = String(row.status || "pending").toLowerCase();
      todo.is_completed = todo.status === "completed" ? 1 : 0;
      if (row.is_completed !== undefined && row.is_completed !== null) {
        todo.is_completed = normalize_Boolean(row.is_completed);
      }
      todo.completed_at = row.completed_at || row.completed_date || "";
      todo.created_at = row.created_at || "";
      todo.updated_at = row.updated_at || "";
      todo.is_deleted = normalize_Boolean(row.is_deleted);
      if (todo.priority !== "high" && todo.priority !== "medium" && todo.priority !== "low") {
        todo.priority = "normal";
      }
      return todo;
    },
    normalize_Response = function(response) {
      var list = [];
      if ($.isArray(response)) {
        list = response;
      }
      else if (response && $.isArray(response.data)) {
        list = response.data;
      }
      else if (response && $.isArray(response.todos)) {
        list = response.todos;
      }
      else if (response && $.isArray(response.result)) {
        list = response.result;
      }
      return $.map(list, function(row) {
        return normalize_Todo(row);
      });
    },
    parse_Date = function(value) {
      if (!value) {
        return null;
      }
      var str = String(value),
        match,
        parsed;
      match = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
      if (match) {
        return new Date(parseInt(match[1], 10), parseInt(match[2], 10) - 1, parseInt(match[3], 10));
      }
      match = str.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
      if (match) {
        return new Date(parseInt(match[3], 10), parseInt(match[2], 10) - 1, parseInt(match[1], 10));
      }
      parsed = new Date(str);
      if (!isNaN(parsed.getTime())) {
        return parsed;
      }
      return null;
    },
    format_Date = function(value) {
      var date = parse_Date(value);
      if (!date) {
        return "";
      }
      var day = date.getDate(),
        month = date.getMonth() + 1,
        year = date.getFullYear();
      if (day < 10) {
        day = "0" + day;
      }
      if (month < 10) {
        month = "0" + month;
      }
      return day + "/" + month + "/" + year;
    },
    is_Today = function(todo) {
      if (!todo.date || todo.is_completed == 1) {
        return false;
      }
      var date = parse_Date(todo.date);
      if (!date) {
        return false;
      }
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      date.setHours(0, 0, 0, 0);
      return date.getTime() === today.getTime();
    },
    is_Upcoming = function(todo) {
      if (!todo.date || todo.is_completed == 1) {
        return false;
      }
      var date = parse_Date(todo.date);
      if (!date) {
        return false;
      }
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      date.setHours(0, 0, 0, 0);
      return date.getTime() > today.getTime();
    },
    is_Overdue = function(todo) {
      if (!todo.date || todo.is_completed == 1) {
        return false;
      }
      var date = parse_Date(todo.date);
      if (!date) {
        return false;
      }
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      date.setHours(0, 0, 0, 0);
      return date.getTime() < today.getTime();
    },
    priority_Weight = function(priority) {
      switch (priority) {
        case "high":
          return 3;
        case "medium":
          return 2;
        case "low":
          return 1;
        default:
          return 0;
      }
    },
    priority_Icon = function(priority) {
      switch (priority) {
        case "high":
          return "fa-warning";
        case "medium":
          return "fa-exclamation-circle";
        case "low":
          return "fa-flag";
        default:
          return "fa-minus-circle";
      }
    },
    compare_Dates = function(a, b) {
      var date_a = parse_Date(a),
        date_b = parse_Date(b);
      if (!date_a && !date_b) {
        return 0;
      }
      if (!date_a) {
        return 1;
      }
      if (!date_b) {
        return -1;
      }
      return date_a.getTime() - date_b.getTime();
    },
    sort_Todos = function(list) {
      list.sort(function(a, b) {
        var weight_a = priority_Weight(a.priority),
          weight_b = priority_Weight(b.priority);
        if (weight_a !== weight_b) {
          return weight_b - weight_a;
        }
        return compare_Dates(a.date, b.date);
      });
    },
    show_Loading = function() {
      $(".todo-page-list").html('<div class="todo-page-empty"><i class="fa fa-spinner fa-spin"></i><span>Loading tasks...</span></div>');
    },
    show_Error = function() {
      $(".todo-page-list").html('<div class="todo-page-empty"><i class="fa fa-exclamation-circle"></i><span>Unable to load Todo list.</span></div>');
      $("#todo-count-all").text("0");
      $("#todo-count-pending").text("0");
      $("#todo-count-overdue").text("0");
      $("#todo-count-completed").text("0");
    },
    load_Todos = function() {
      show_Loading();
      $.get("?action=" + todo_load_action, {}, function(response) {
        todo_data = normalize_Response(response);
        clean_Selected_Todos();
        update_Counters();
        render_Page();
        update_Selection_UI();
      }, "json").fail(function(xhr) {
        cargohub.its.http_fail(xhr, "User.AllTodo::Load_Todos");
        show_Error();
      });
    },
    clean_Selected_Todos = function() {
      var existing = {};
      $.each(todo_data, function(index, todo) {
        existing[String(todo.id)] = true;
      });
      $.each(selected_todos, function(id) {
        if (!existing[id]) {
          delete selected_todos[id];
        }
      });
    },
    update_Counters = function() {
      var total = 0,
        pending = 0,
        overdue = 0,
        completed = 0;
      $.each(todo_data, function(index, todo) {
        if (todo.is_deleted == 1) {
          return;
        }
        total++;
        if (todo.is_completed == 1) {
          completed++;
        }
        else {
          pending++;
          if (is_Overdue(todo)) {
            overdue++;
          }
        }
      });
      $("#todo-count-all").text(total);
      $("#todo-count-pending").text(pending);
      $("#todo-count-overdue").text(overdue);
      $("#todo-count-completed").text(completed);
    },
    get_Filtered_Todos = function() {
      var result = [];
      $.each(todo_data, function(index, todo) {
        if (todo.is_deleted == 1) {
          return;
        }
        if (current_filter === "pending" && (todo.is_completed != 0 || is_Overdue(todo))) {
          return;
        }
        if (current_filter === "completed" && todo.is_completed != 1) {
          return;
        }
        if (current_filter === "overdue" && !is_Overdue(todo)) {
          return;
        }
        if (current_priority !== "" && todo.priority !== current_priority) {
          return;
        }
        if (current_search !== "") {
          var searchable = (todo.title + " " + todo.description + " " + todo.remark + " " + todo.priority + " " + todo.date).toLowerCase();
          if (searchable.indexOf(current_search) === -1) {
            return;
          }
        }
        result.push(todo);
      });
      return result;
    },
    render_Page = function() {
      var filtered = get_Filtered_Todos(),
        overdue = [],
        today = [],
        upcoming = [],
        no_due_date = [],
        completed = [],
        deleted = [];

      $.each(filtered, function(index, todo) {
        if (todo.is_completed == 1) {
          completed.push(todo);
        }
        else if (is_Overdue(todo)) {
          overdue.push(todo);
        }
        else if (is_Today(todo)) {
          today.push(todo);
        }
        else if (is_Upcoming(todo)) {
          upcoming.push(todo);
        }
        else {
          no_due_date.push(todo);
        }
      });

      sort_Todos(overdue);
      sort_Todos(today);
      sort_Todos(upcoming);
      sort_Todos(no_due_date);
      sort_Todos(completed);

      $.each(todo_data, function(index, todo) {
        if (todo.is_deleted == 1) {
          deleted.push(todo);
        }
      });

      deleted.sort(function(a, b) {
        var date_a = parse_Date(a.updated_at || a.created_at),
          date_b = parse_Date(b.updated_at || b.created_at);

        if (!date_a && !date_b) {
          return 0;
        }
        if (!date_a) {
          return 1;
        }
        if (!date_b) {
          return -1;
        }

        return date_b.getTime() - date_a.getTime();
      });

      var groups = $(".todo-group");

      if (groups.length < 5) {
        return;
      }

      render_Group(groups.eq(0), overdue, "overdue");
      render_Group(groups.eq(1), today, "today");
      render_Group(groups.eq(2), upcoming, "upcoming");
      render_Group(groups.eq(3), no_due_date, "no_due_date");
      render_Group(groups.eq(4), completed, "completed");

      if (groups.length >= 6) {
        render_Deleted_Group(groups.eq(5), deleted);
      }

      groups.hide();

      if (current_filter === "all") {
        groups.show();
      }
      
      else if (current_filter === "active") {
        if (overdue.length > 0) {
          groups.eq(0).show();
        }
        if (today.length > 0) {
          groups.eq(1).show();
        }
        if (upcoming.length > 0) {
          groups.eq(2).show();
        }
        if (no_due_date.length > 0) {
          groups.eq(3).show();
        }
        if (!overdue.length && !today.length && !upcoming.length && !no_due_date.length) {
          groups.eq(1).show();
        }
      }
      else if (current_filter === "pending") {
        if (overdue.length > 0) {
          groups.eq(0).show();
        }
        if (today.length > 0) {
          groups.eq(1).show();
        }
        if (upcoming.length > 0) {
          groups.eq(2).show();
        }
        if (no_due_date.length > 0) {
          groups.eq(3).show();
        }
        if (!overdue.length && !today.length && !upcoming.length && !no_due_date.length) {
          groups.eq(1).show();
        }
      }
      else if (current_filter === "overdue") {
        groups.eq(0).show();
      }
      else if (current_filter === "completed") {
        groups.eq(4).show();
        groups.eq(5).show();
      }
    },
    render_Deleted_Group = function($group, list) {
      var html = "";

      $group.find(".todo-group-count").text(
        list.length + (list.length == 1 ? " task" : " tasks")
      );

      if (!list.length) {
        html =
          '<div class="todo-page-empty todo-empty-deleted">' +
            '<i class="fa fa-trash-o"></i>' +
            '<span>Trash is empty</span>' +
          '</div>';

        $group.find(".todo-page-list").html(html);
        return;
      }

      $.each(list, function(index, todo) {
        html += render_Deleted_Item(todo);
      });

      $group.find(".todo-page-list").html(html);
    },
    render_Deleted_Item = function(todo) {
      var id = String(todo.id),
        priority = todo.priority || "normal",
        priority_icon = priority_Icon(priority),
        priority_text = capitalize(priority),
        date_text = format_Date(todo.date);

      return (
        '<div class="todo-page-item todo-deleted-item" data-id="' +
          escape_Html(id) +
        '">' +
          '<div class="todo-page-check todo-deleted-icon">' +
            '<i class="fa fa-trash-o"></i>' +
          '</div>' +
          '<div class="todo-page-main">' +
            '<div class="todo-page-item-title">' +
              escape_Html(todo.title) +
            '</div>' +
            (todo.description !== ""
              ? '<div class="todo-page-item-info">' +
                  escape_Html(todo.description) +
                '</div>'
              : '') +
            '<div class="todo-page-item-meta">' +
              (date_text !== ""
                ? '<span>' +
                    '<i class="fa fa-calendar"></i>' +
                    escape_Html(date_text) +
                  '</span>'
                : '') +
              '<span>' +
                '<i class="fa ' + escape_Html(priority_icon) + '"></i>' +
                escape_Html(priority_text) +
              '</span>' +
            '</div>' +
          '</div>' +
          '<div class="todo-deleted-actions">' +
            '<a href="javascript:void(0)" class="todo-page-permanent-delete" onclick="cargohub.user_alltodo.permanentDeleteTodo(' +
              escape_Html(id) +
            '); return false;" title="Delete Forever">' +
              '<i class="fa fa-trash-o"></i><span>Delete Forever</span>' +
            '</a>' +
          '</div>' +
        '</div>'
      );
    },
    render_Group = function($group, list, group_type) {
      var html = "";

      $group.find(".todo-group-count").text(
        list.length + (list.length == 1 ? " task" : " tasks")
      );

      if (!list.length) {
        var icon = "fa-check-circle-o",
          text = "Nothing here";

        if (group_type === "overdue") {
          icon = "fa-check-circle-o";
          text = "You're all caught up";
        }
        else if (group_type === "today") {
          icon = "fa-calendar-check-o";
          text = "You're all clear for today";
        }
        else if (group_type === "upcoming") {
          icon = "fa-clock-o";
          text = "No upcoming tasks";
        }
        else if (group_type === "no_due_date") {
          icon = "fa-calendar-o";
          text = "No tasks without a due date";
        }
        else if (group_type === "completed") {
          icon = "fa-check-circle-o";
          text = "No completed tasks yet";
        }

        html =
          '<div class="todo-page-empty todo-empty-' + group_type + '">' +
            '<i class="fa ' + icon + '"></i>' +
            '<span>' + text + '</span>' +
          '</div>';

        $group.find(".todo-page-list").html(html);
        return;
      }

      $.each(list, function(index, todo) {
        html += render_Item(todo);
      });

      $group.find(".todo-page-list").html(html);
    },
    render_Item = function(todo) {
      var id = String(todo.id),
        is_selected = selected_todos[id] === true,
        item_class = "",
        priority = todo.priority || "normal",
        priority_icon = priority_Icon(priority),
        priority_text = capitalize(priority),
        meta = "";

      if (todo.is_completed == 1) {
        item_class = " todo-page-item-completed";
      }
      else if (is_Overdue(todo)) {
        item_class = " todo-overdue";
      }

      if (is_selected) {
        item_class += " todo-selected";
      }

      var date_text = format_Date(todo.date);

      if (date_text !== "") {
        meta += '<span><i class="fa fa-calendar"></i>' +
          escape_Html(date_text) +
          '</span>';
      }

      if (todo.is_completed == 1) {
        var completed_date = format_Date(todo.completed_at || todo.date);

        if (completed_date !== "") {
          meta += '<span><i class="fa fa-check"></i>Completed ' +
            escape_Html(completed_date) +
            '</span>';
        }
      }
      else if (is_Today(todo)) {
        meta += '<span><i class="fa fa-clock-o"></i>Today</span>';
      }
      else if (is_Overdue(todo)) {
        meta += '<span class="todo-meta-overdue"><i class="fa fa-exclamation-circle"></i>Overdue</span>';
      }
      else if (is_Upcoming(todo)) {
        meta += '<span><i class="fa fa-clock-o"></i>Upcoming</span>';
      }
      else {
        meta += '<span><i class="fa fa-calendar-o"></i>No due date</span>';
      }

      return (
        '<div class="todo-page-item' + item_class + '" data-id="' +
          escape_Html(id) + '">' +
          '<div class="todo-page-check">' +
            '<input type="checkbox" class="todo-select-checkbox" value="' +
              escape_Html(id) + '"' +
              (is_selected ? ' checked="checked"' : '') +
              ' onclick="event.stopPropagation();">' +
          '</div>' +
          '<div class="todo-page-main" onclick="cargohub.user_alltodo.openTodo(' +
            escape_Html(id) + ');">' +
            '<div class="todo-page-item-title">' +
              escape_Html(todo.title) +
            '</div>' +
            (todo.description !== ""
              ? '<div class="todo-page-item-info">' +
                  escape_Html(todo.description) +
                '</div>'
              : '') +
            (meta !== ""
              ? '<div class="todo-page-item-meta">' + meta + '</div>'
              : '') +
          '</div>' +
          '<span class="todo-page-priority todo-page-priority-' +
            escape_Html(priority) + '">' +
            '<i class="fa ' + priority_icon + '"></i>' +
            escape_Html(priority_text) +
          '</span>' +
          '<a href="javascript:void(0)" class="todo-page-view" onclick="cargohub.user_alltodo.openTodo(' +
            escape_Html(id) +
            '); return false;" title="View Todo">' +
            '<i class="fa fa-eye"></i>' +
          '</a>' +
        '</div>'
      );
    },
    get_Selected_Ids = function() {
      var ids = [];

      $.each(selected_todos, function(id, selected) {
        if (selected) {
          ids.push(String(id));
        }
      });

      return ids;
    },
    get_Selected_State = function() {
      var ids = get_Selected_Ids(),
        pending = 0,
        completed = 0;

      if (!ids.length) {
        return "none";
      }

      $.each(ids, function(index, id) {
        $.each(todo_data, function(i, todo) {
          if (String(todo.id) === String(id)) {
            if (todo.is_completed == 1) {
              completed++;
            }
            else {
              pending++;
            }
            return false;
          }
        });
      });

      if (completed > 0 && pending === 0) {
        return "completed";
      }

      if (pending > 0 && completed === 0) {
        return "pending";
      }

      return "mixed";
    },
    update_Selection_UI = function() {
      var ids = get_Selected_Ids(),
        count = ids.length,
        state = get_Selected_State();

      if (count > 0) {
        $("#todo-selection-info").css("display", "inline-flex").text(count + " selected");
        $("#todo-delete-selected-btn").removeClass("disabled");
        $("#todo-bulk-status-btn").removeClass("disabled");

        if (state === "completed") {
          $("#todo-bulk-status-btn").addClass("todo-action-undo");
          $("#todo-bulk-status-btn i").attr("class", "fa fa-undo");
          $("#todo-bulk-status-btn span").text("Undo");
        }
        else {
          $("#todo-bulk-status-btn").removeClass("todo-action-undo");
          $("#todo-bulk-status-btn i").attr("class", "fa fa-check");
          $("#todo-bulk-status-btn span").text("Complete");
        }
      }
      else {
        $("#todo-selection-info").hide();
        $("#todo-delete-selected-btn").addClass("disabled");
        $("#todo-bulk-status-btn").addClass("disabled").removeClass("todo-action-undo");
        $("#todo-bulk-status-btn i").attr("class", "fa fa-check");
        $("#todo-bulk-status-btn span").text("Complete");
      }

      var checkboxes = $("#todo-page .todo-select-checkbox"),
        checked = checkboxes.filter(":checked").length;

      $("#todo-select-all").prop(
        "checked",
        (checkboxes.length > 0 && checked === checkboxes.length)
      );
    },
    toggle_Selected_Status = function() {
      var ids = get_Selected_Ids();

      if (!ids.length) {
        cargohub.alert("Please select at least one Todo.", "Todo");
        return;
      }

      var state = get_Selected_State();

      if (state === "completed") {
        undo_Selected(ids);
        return;
      }

      complete_Selected(ids);
    },
    complete_Selected = function(ids) {
      var targetIds = [];

      $.each(ids, function(index, id) {
        $.each(todo_data, function(i, todo) {
          if (String(todo.id) === String(id)) {
            if (todo.is_completed != 1) {
              targetIds.push(id);
            }
            return false;
          }
        });
      });

      if (!targetIds.length) {
        return;
      }

      if (!confirm(
        "Mark " + targetIds.length +
        " selected Todo" +
        (targetIds.length > 1 ? "s" : "") +
        " as completed?"
      )) {
        return;
      }

      bulk_Update_Status(targetIds, "completed", "Complete");
    },
    undo_Selected = function(ids) {
      if (!confirm(
        "Undo completion for " + ids.length +
        " selected Todo" +
        (ids.length > 1 ? "s" : "") +
        "?"
      )) {
        return;
      }

      bulk_Update_Status(ids, "pending", "Undo");
    },
    bulk_Update_Status = function(ids, status, action_name) {
      var done = 0,
        failed = 0,
        total = ids.length;

      cargohub.components.spinner.On(
        action_name + "...",
        "Updating selected Todo items",
        null,
        null,
        300
      );

      $.each(ids, function(index, id) {
        $.post(
          "/user/overview?action=saveTodo",
          {
            id: id,
            status: status
          },
          function(response) {
            if (response && response.success) {
              done++;
            }
            else {
              failed++;
            }

            if (done + failed === total) {
              finish_Status_Update(done, failed, action_name);
            }
          },
          "json"
        ).fail(function() {
          failed++;

          if (done + failed === total) {
            finish_Status_Update(done, failed, action_name);
          }
        });
      });
    },
    finish_Status_Update = function(done, failed, action_name) {
      cargohub.components.spinner.Off();
      selected_todos = {};
      load_Todos();

      if (typeof loadTodoList === "function") {
        loadTodoList();
      }

      if (failed > 0) {
        cargohub.alert(
          done + " Todo" + (done !== 1 ? "s" : "") +
          " " +
          (action_name === "Undo" ? "undone" : "completed") +
          ". " +
          failed +
          " could not be updated.",
          "Todo Update"
        );
      }
    },
    delete_Selected = function() {
      var ids = get_Selected_Ids();

      if (!ids.length) {
        cargohub.alert("Please select at least one Todo.", "Delete Todo");
        return;
      }

      Swal.fire({
        title: "Delete Todo?",
        text: "Are you sure you want to delete " +
          ids.length +
          " selected Todo" +
          (ids.length > 1 ? "s" : "") +
          "?",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#dc2626",
        cancelButtonColor: "#64748b",
        confirmButtonText: "Yes, Delete",
        cancelButtonText: "Cancel"
      }).then(function(result) {
        if (!result.isConfirmed) {
          return;
        }

        bulk_Delete(ids);
      });
    },
    bulk_Delete = function(ids) {
      var done = 0,
        failed = 0,
        total = ids.length;

      cargohub.components.spinner.On(
        "Deleting...",
        "Removing selected Todo items",
        null,
        null,
        300
      );

      $.each(ids, function(index, id) {
        $.post(
          "/user/overview?action=saveTodo",
          {
            id: id,
            is_deleted: "1"
          },
          function(response) {
            if (response && response.success) {
              done++;
            }
            else {
              failed++;
            }

            if (done + failed === total) {
              finish_Delete(done, failed);
            }
          },
          "json"
        ).fail(function() {
          failed++;

          if (done + failed === total) {
            finish_Delete(done, failed);
          }
        });
      });
    },
    finish_Delete = function(done, failed) {
      cargohub.components.spinner.Off();
      selected_todos = {};
      load_Todos();

      if (typeof loadTodoList === "function") {
        loadTodoList();
      }

      if (failed > 0) {
        cargohub.alert(
          done + " Todo" +
          (done !== 1 ? "s" : "") +
          " deleted. " +
          failed +
          " could not be deleted.",
          "Delete Todo"
        );
        return;
      }

      Swal.fire({
        title: "Deleted!",
        text: done + " Todo" +
          (done !== 1 ? "s have" : " has") +
          " been deleted.",
        type: "success",
        timer: 1200,
        showConfirmButton: false
      });
    },
    saveTodo = function() {
      console.log("ddf");

      var id = $("#todo-id").val(),
        title = $.trim($("#todo-title-input").val()),
        date = $("#todo-date-input").val(),
        priority = $("#todo-priority-input").val(),
        description = $("#todo-description-input").val(),
        remark = $("#todo-remark-input").val();

      if (title == "") {
        alert("Please enter todo title.");
        $("#todo-title-input").focus();
        return;
      }

      var saveData = {
        title: title,
        due_date: date,
        priority: priority,
        description: description,
        remark: remark
      };

      if (id != "") {
        saveData.id = id;
      }

      $.post(
        "/user/overview?action=saveTodo",
        saveData,
        function(response) {
          console.log("Save Todo API:", response);

          if (!response || !response.success) {
            alert("Unable to save todo.");
            return;
          }

          closeTodoPopup();

          if (typeof loadTodoList === "function") {
            loadTodoList();
          }

          if (
            cargohub.user_alltodo &&
            typeof cargohub.user_alltodo.refresh === "function"
          ) {
            cargohub.user_alltodo.refresh();
          }
        },
        "json"
      ).fail(function(xhr) {
        console.log("Save Todo error:", xhr);
        alert("Unable to save todo.");
      });
    },
    openTodoPopup = function(id) {
      console.log("openTodoPopup", id);
      $("#todo-popup").fadeIn(150);

      if (!id) {
        $("#todo-id").val("");
        $("#todo-popup-heading").text("Add Todo");
        $("#todo-title-input").val("");
        $("#todo-date-input").val("");
        $("#todo-priority-input").val("medium");
        $("#todo-description-input").val("");
        $("#todo-remark-input").val("");
        $("#todo-delete-btn").hide();
        $("#todo-save-text").text("Add Todo");
        return;
      }

      $("#todo-popup-heading").text("Todo Details");
      $("#todo-delete-btn").show();
      $("#todo-save-text").text("Save");
      $("#todo-id").val(id);
      $("#todo-title-input").val("");
      $("#todo-date-input").val("");
      $("#todo-priority-input").val("medium");
      $("#todo-description-input").val("");
      $("#todo-remark-input").val("");

      $.post(
        "/user/overview?action=getTodoDetails",
        {
          id: id
        },
        function(response) {
          console.log("Todo Details API:", response);

          if (!response || !response.success || !response.data) {
            alert("Unable to load Todo details.");
            return;
          }

          var todo = response.data;

          $("#todo-id").val(todo.id || "");
          $("#todo-title-input").val(todo.title || "");
          $("#todo-date-input").val(todo.due_date || "");
          $("#todo-priority-input").val(todo.priority || "medium");
          $("#todo-description-input").val(todo.description || "");
          $("#todo-remark-input").val(todo.remark || "");
        },
        "json"
      ).fail(function(xhr) {
        console.log("Todo Details error:", xhr);
        alert("Unable to load Todo details.");
      });
    },
    closeTodoPopup = function() {
      $("#todo-popup").fadeOut(150);
    },
    deleteTodo = function() {
      var id = $("#todo-id").val();

      if (!id) {
        return;
      }

      if (!confirm("Are you sure you want to delete this todo?")) {
        return;
      }

      $.post(
        "/user/overview?action=saveTodo",
        {
          id: id,
          is_deleted: "1"
        },
        function(response) {
          if (!response || !response.success) {
            alert("Unable to delete todo.");
            return;
          }

          closeTodoPopup();

          if (typeof loadTodoList === "function") {
            loadTodoList();
          }

          if (
            cargohub.user_alltodo &&
            typeof cargohub.user_alltodo.refresh === "function"
          ) {
            cargohub.user_alltodo.refresh();
          }

          alert("Todo has been deleted.");
        },
        "json"
      ).fail(function(xhr) {
        console.log("Delete Todo error:", xhr);
        alert("Unable to delete todo.");
      });
    },
    open_Todo = function(id) {
      if (typeof openTodoPopup === "function") {
        openTodoPopup(id);
        return;
      }

      alert("Todo popup is not available on this page.");
    },
    bind_Events = function() {
      $(document)
        .off("click.alltodoFilter", "#todo-page .todo-filter")
        .on(
          "click.alltodoFilter",
          "#todo-page .todo-filter",
          function(e) {
            e.preventDefault();

            $("#todo-page .todo-filter").removeClass("active");
            $(this).addClass("active");

            current_filter =
              $(this).attr("data-filter") || "all";

            render_Page();
            update_Selection_UI();
          }
        );

      $(document)
        .off("keyup.alltodoSearch", "#todo-page-search")
        .on(
          "keyup.alltodoSearch",
          "#todo-page-search",
          function() {
            current_search =
              $.trim($(this).val()).toLowerCase();

            render_Page();
            update_Selection_UI();
          }
        );

      $(document)
        .off("change.alltodoPriority", "#todo-priority-filter")
        .on(
          "change.alltodoPriority",
          "#todo-priority-filter",
          function() {
            current_priority =
              $(this).val() || "";

            render_Page();
            update_Selection_UI();
          }
        );

      $(document)
        .off(
          "change.alltodoSelect",
          "#todo-page .todo-select-checkbox"
        )
        .on(
          "change.alltodoSelect",
          "#todo-page .todo-select-checkbox",
          function() {
            var id = String($(this).val());

            if ($(this).is(":checked")) {
              selected_todos[id] = true;
              $(this)
                .closest(".todo-page-item")
                .addClass("todo-selected");
            }
            else {
              delete selected_todos[id];
              $(this)
                .closest(".todo-page-item")
                .removeClass("todo-selected");
            }

            update_Selection_UI();
          }
        );

      $(document)
        .off("change.alltodoSelectAll", "#todo-select-all")
        .on(
          "change.alltodoSelectAll",
          "#todo-select-all",
          function() {
            var checked = $(this).is(":checked");

            $("#todo-page .todo-select-checkbox").each(function() {
              var id = String($(this).val());

              $(this).prop("checked", checked);

              if (checked) {
                selected_todos[id] = true;
                $(this)
                  .closest(".todo-page-item")
                  .addClass("todo-selected");
              }
              else {
                delete selected_todos[id];
                $(this)
                  .closest(".todo-page-item")
                  .removeClass("todo-selected");
              }
            });

            update_Selection_UI();
          }
        );
    };

  this.init = function() {
    if (!$("#todo-page").length) {
      return;
    }

    bind_Events();
    show_Loading();
    load_Todos();
  };

  this.toggleSelectedStatus = function() {
    toggle_Selected_Status();
  };

  this.deleteSelected = function() {
    delete_Selected();
  };

  this.completeTodo = function(id) {
    if (!id) {
      return;
    }

    $.post(
      "/user/overview?action=saveTodo",
      {
        id: id,
        status: "completed"
      },
      function(response) {
        if (!response || !response.success) {
          cargohub.alert("Unable to complete Todo.", "Todo");
          return;
        }

        delete selected_todos[String(id)];
        load_Todos();

        if (typeof loadTodoList === "function") {
          loadTodoList();
        }
      },
      "json"
    );
  };

  this.undoTodo = function(id) {
    if (!id) {
      return;
    }

    $.post(
      "/user/overview?action=saveTodo",
      {
        id: id,
        status: "pending"
      },
      function(response) {
        if (!response || !response.success) {
          cargohub.alert("Unable to undo Todo.", "Todo");
          return;
        }

        delete selected_todos[String(id)];
        load_Todos();

        if (typeof loadTodoList === "function") {
          loadTodoList();
        }
      },
      "json"
    );
  };

  this.deleteTodo = function(id) {
    if (!id) {
      return;
    }

    Swal.fire({
      title: "Delete Todo?",
      text: "Are you sure you want to delete this todo?",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#dc2626",
      cancelButtonColor: "#64748b",
      confirmButtonText: "Yes, Delete",
      cancelButtonText: "Cancel"
    }).then(function(result) {
      if (!result.isConfirmed) {
        return;
      }

      $.post(
        "/user/overview?action=saveTodo",
        {
          id: id,
          is_deleted: "1"
        },
        function(response) {
          if (!response || !response.success) {
            cargohub.alert("Unable to delete Todo.", "Todo");
            return;
          }

          delete selected_todos[String(id)];
          load_Todos();

          if (typeof loadTodoList === "function") {
            loadTodoList();
          }

          Swal.fire({
            title: "Deleted!",
            text: "Todo has been deleted.",
            type: "success",
            timer: 1200,
            showConfirmButton: false
          });
        },
        "json"
      );
    });
  };

  this.restoreTodo = function(id) {
    if (!id) {
      return;
    }

    if (!confirm("Restore this Todo?")) {
      return;
    }

    $.post(
      "/user/overview?action=saveTodo",
      {
        id: id,
        is_deleted: "0"
      },
      function(response) {
        if (!response || !response.success) {
          cargohub.alert("Unable to restore Todo.", "Restore Todo");
          return;
        }

        delete selected_todos[String(id)];
        load_Todos();

        if (typeof loadTodoList === "function") {
          loadTodoList();
        }

        Swal.fire({
          title: "Restored!",
          text: "Todo has been restored.",
          type: "success",
          timer: 1200,
          showConfirmButton: false
        });
      },
      "json"
    ).fail(function() {
      cargohub.alert("Unable to restore Todo.", "Restore Todo");
    });
  };

  this.permanentDeleteTodo = function(id) {
    if (!id) {
      return;
    }

    Swal.fire({
      title: "Delete Permanently?",
      text: "This Todo will be permanently deleted and cannot be restored.",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#dc2626",
      cancelButtonColor: "#64748b",
      confirmButtonText: "Delete Forever",
      cancelButtonText: "Cancel"
    }).then(function(result) {
      if (!result.isConfirmed) {
        return;
      }

      $.post(
        "/user/overview?action=saveTodo",
        {
          id: id,
          permanent_delete: "1"
        },
        function(response) {
          if (!response || !response.success) {
            cargohub.alert(
              "Unable to permanently delete Todo.",
              "Delete Todo"
            );
            return;
          }

          delete selected_todos[String(id)];
          load_Todos();

          if (typeof loadTodoList === "function") {
            loadTodoList();
          }

          Swal.fire({
            title: "Permanently Deleted!",
            text: "Todo has been permanently deleted.",
            type: "success",
            timer: 1200,
            showConfirmButton: false
          });
        },
        "json"
      );
    });
  };

  this.openTodo = function(id) {
    open_Todo(id);
  };

  this.saveTodo = function() {
    saveTodo();
  };

  this.reload = function() {
    load_Todos();
  };

  this.refresh = function() {
    load_Todos();

    if (typeof loadTodoList === "function") {
      loadTodoList();
    }
  };

  this.showLoading = function() {
    show_Loading();
  };

  this.showError = function() {
    show_Error();
  };
  this.addTodo = function() {
  openTodoPopup();
};
this.closeTodoPopup = function() {
  closeTodoPopup();
};
$(document).on('click', '.todo-group-header', function () {
    $(this).closest('.todo-group').toggleClass('collapsed');
});
});