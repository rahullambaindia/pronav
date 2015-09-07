//used by andrew for namespacing the various js files - to avoid conflicts.  
pronav.project = {};

var project = {

    stage: {
        "POTENTIAL": 1,
        "RFP": 2,
        "ESTIMATING": 3,
        "PROPOSED": 4,
        "AUTHORIZED": 5,
        "OPEN": 6,
        "HOLD": 7,
        "CANCELLED": 8,
        "CLOSED": 9
    },

    propsal_type : {
        "TM" : 1,
        "TM_NOT-TO-EXCEED" : 2,
        "FIXED_PRICE" : 3,
        "WARRANTY" : 4,
        "SERVICE_CONTRACT" : 5,
        "APPROX_VALUE" : 6,
        "BUDGET_PROPOSAL" : 7
    },

    init : function(){ 
        $("#top-tab li").removeClass("ui-tabs-selected");
        $("#menu-projects").addClass("ui-tabs-selected");                
    },   

    create: {

        animating: false,
        teamMembers: {},
        fetchedCorps: [],
        tempProposalType: null, //fix for IE 7 which clears out the radio buttons value when hidden.

        _init : function(){

            $("#schedule-not-before, #schedule-required-by, #schedule-estimated-start, #schedule-estimated-end").datepicker({
                changeMonth: true, 
                onClose: function(){$(this).focus();},
                onSelect: function(){$(this).focus();},
                dateFormat: 'm/d/y'
            });

            $("#acq_bid_date, #acq_pre_bid_date, #acq_bid_review_meeting, #acq_job_turnover_meeting, #acq_bid_descope_meeting").datetimepicker({
                ampm: true,
                stepHour: 1,
                stepMinute: 15,
                changeMonth: true,
                changeYear: true,
                showOtherMonths: true,
                selectOtherMonths: true,
                dateFormat: 'm/d/y',
                timeFormat : 'hh:mm TT'
            });                        

            $("#done-for-corporation-autocomplete").autocompletePlus({
                list_field : "#done-for-corporation",
                label : 'Search Customers',
                source : '/corporation/search',
                minLength: 2
            });
            $("#done-for-corporation").on('change', project.create.doneForCorporationChanged);
                              
            $("#location-owner-autocomplete").autocompletePlus({
                list_field : "#location-owner",
                label : 'Search Location Owners',
                source : '/corporation/search',
                minLength: 2                
            });
            $("#location-owner").on('change', function(){project.create.locationOwnerChanged(null)});

            $("#auto_acq_sales_person").autocompletePlus({
                list_field : "#acq_sales_person",
                label : 'Search Sales Persons',
                source : '/user/search/?format=FL&activetrim=1'
            });

            $("#auto_acq_estimator").autocompletePlus({
                list_field : "#acq_estimator",
                label : 'Search Estimators',
                source : '/user/search/?format=FL&activetrim=1'
            }); 
        },

        showAsWorking: function (showAsWorking) {
            showAsWorking = (typeof showAsWorking == 'boolean' ? showAsWorking : true);
            if (showAsWorking) {
                $("#createBtnRow input[type=button]").button({ disabled: true });
                $("#create_loading").show();
            } else {
                $("#createBtnRow input[type=button]").button({ disabled: false });
                $("#create_loading").hide();
            }
        },

        slidePanel: function () {
            var isOnStep1 = $("#step1").is(':visible');
            if (isOnStep1) {
                $("#step1").hide('slide', function () {
                    $("#step2").show('slide');
                    project.create.animating = false;
                    project.create.showAsWorking(false);
                });
            } else {
                $("#step2").hide('slide', function () {
                    $("#step1").show('slide', function () {
                        //IE7 clears the radio button value, I will set it back if you go back to the first panel.
                        if (pronav.isIE7() && !isNaN(project.create.tempProposalType)) {
                            $("input[name=authorization-type][value=" + project.create.tempProposalType + "]").attr('checked', true).click();
                        }
                    });
                    project.create.animating = false;
                    project.create.showAsWorking(false);
                });
            }
        },

        IE7Bug: function () {
            if (pronav.isIE7()) {
                $("#available-users, #assigned-users").width(276);
            }
        },

        createNext: function (isEmployee) {

            if (project.create.animating) { return; }
            project.create.showAsWorking();

            isEmployee = (typeof isEmployee == 'boolean' ? isEmployee : false);

            if ($('#step1').is(':visible')) {

                var data = project.create.getData(isEmployee);

                if (!project.create.isValid(data, isEmployee)) {
                    project.create.showAsWorking(false);
                    return;
                }

                //fix for IE7 - which clears the value when hidden. 
                project.create.tempProposalType = data.stage_id;   

                //check that all necessary info is filled out okay before advancing.
                $.post('/project/get-initial-project-team-members', data, function (o) {
                    if (!(o instanceof Object)) {
                        pronav.error({ msg: 'An Unknown Error Occurred. Please Try Again.' });
                        project.create.showAsWorking(false);
                    } else if (o.state != 1) {
                        pronav.error({ msg: "Please Correct the Following:", errors: o.msgs });
                        project.create.showAsWorking(false);
                    } else {
                        project.create.animating = true;
                        project.create.slidePanel();
                        $('#btn-create, #team_notifications_notice').show();                        
                        $('#btn-next').val('Previous');

                        //clear out the fetched list of corporations and add the current corp.
                        project.create.fetchedCorps = [];
                        project.create.fetchedCorps.push(data.done_for_corporation);

                        //clear out the cache of team members
                        project.create.teamMembers = {};

                        //add the list of current users - using their id as they object key.
                        for (var i = 0; i < o.users.length; i++) {
                            var user = o.users[i];
                            if (user.pronav_access != '1'){
                                user.name = user.name + " *"
                            } 
                            project.create.teamMembers[user.user_id] = user;
                        }

                        //be sure to add the point of contact to the team by default.
                        var poc = data.point_of_contact;
                        if (project.create.teamMembers[poc] != undefined && data.stage_id != project.stage.POTENTIAL) {
                            project.create.teamMembers[poc].assigned = 1;
                        }

                        //set the project team selection drop down to the corporation id you just fetched. 
                        if (data.stage_id == project.stage.POTENTIAL || data.stage_id == project.stage.CANCELLED){
                            $('#corporation_select option:not([value='+pronav.trim_id+'])').attr('disabled','disabled');
                            $("#corporation_select").val(pronav.trim_id).trigger('change');
                            $('#step2 span.section-header').empty().append('Select The Initial Project Team Members<sup>&#10013;</sup>');
                            $('#project_type_internal').show();
                        } else {
                            $('#corporation_select option').removeAttr('disabled');
                            $("#corporation_select").val(data.done_for_corporation).trigger('change');
                            $('#step2 .span.section-header').empty().append('Select The Initial Project Team Members');
                            $('#project_type_internal').hide();
                        }

                    }
                    }, 'json');
            } else {

                project.create.slidePanel();
                $('#btn-create, #team_notifications_notice').hide();
                $('#btn-next').val('Next');
            }
        },

        getData: function (isEmployee, useTemp) {
            isEmployee = (typeof isEmployee == "boolean" ? isEmployee : false);
            var p = {};
            p.done_for_corporation = $("#done-for-corporation").val();
            p.done_for_workgroup = $("#done-for-workgroup").val();
            p.done_at_location = $("#done-at-location").val();
            p.title = $("#project-title").val();
            p.scope = $("#scope").val();
            p.done_by_workgroup = $("#done-by-workgroup").val();
            p.location_owner = $("#location-owner").val();
            p.stage_id = (isEmployee ? $("#stage-id").val() : $("input[name=authorization-type]:checked").val());


            /* for non-employees only. Needed to determine the stage */
            p.authorization = $("input[name=authorization-type]:checked").val(); 

            //fix for IE7 which for some reason clears out the value of the radio button once hidden.
            if (useTemp && !isNaN(project.create.tempProposalType) && p.stage_id == undefined) {
                p.stage_id = project.create.tempProposalType;
            }

            p.po_number = $("#po-number").val();
            p.po_date = $("#po-date").val();
            p.po_amount = $("#po-amount").val();
            p.work_type = $("#work-type").val();
            p.point_of_contact = $("#point-of-contact").val();
            p.ref_no = $("#ref-no").val();
            p.requested_by = $("#requested-by").val();
            p.schedule_estimated_start = $("#schedule-estimated-start").val();
            p.schedule_estimated_end = $("#schedule-estimated-end").val();
            p.team = project.create.getSelectedTeamUsers();
            p.comment = $("#comment").val();
            p.acq_sales_person = $("#acq_sales_person").val();
            p.acq_probability = $('#acq_probability').val();
            p.acq_booking_month = $('#acq_booking_month').val();
            p.acq_booking_year = $('#acq_booking_year').val();
            p.acq_estimator = $("#acq_estimator").val();
            p.acq_pre_bid_mandatory = $("#acq_pre_bid_mandatory_yes, #acq_pre_bid_mandatory_no").is(':checked') ? $("#acq_pre_bid_mandatory_yes:checked").length : null;
            p.acq_pre_bid_date = $("#acq_pre_bid_date").val();
            p.acq_bid_date = $("#acq_bid_date").val();
            p.acq_bid_review_meeting = $("#acq_bid_review_meeting").val();
            p.acq_estimating_hours = $("#acq_estimating_hours").val();

            //trim any strings and set any nulls to empty strings.
            for (var i in p){
                if (p[i] == null){
                    p[i] = ""
                } else if (typeof p[i] == "string"){
                    p[i] = pronav.trim(p[i]);
                }
            }

            return p;
        },

        isValid: function (data, isEmployee, doNotAlert) {

            isEmployee = (typeof isEmployee == "boolean" ? isEmployee : false);
            doNotAlert = (typeof doNotAlert == "boolean" ? doNotAlert : false);

            var msgs = [];
            if (data.done_for_corporation != null && data.done_for_corporation == 0) {
                msgs.push('Customer is a required field.');
            }

            if (data.done_for_workgroup != null && data.done_for_workgroup == 0) {
                msgs.push('Workgroup is a required field.');
            }

            if (data.done_at_location == 0) {
                msgs.push('Location is a required field.');
            }

            if (data.title == "") {
                msgs.push('Title is a required field.');
            }

            if (data.scope == "" && !isEmployee) {
                msgs.push('Scope is a required field.');
            }

            if (data.done_by_workgroup == 0) {
                msgs.push('Please select the Business Unit performing the work.');
            }

            if (!isEmployee && data.stage_id == "") {
                msgs.push('Please specify whether you are authorizing a time & material project or requesting a proposal.');
            }

            if (isEmployee && data.stage_id == 0) {
                msgs.push('Project stage is a required field.');
            }

            if (msgs.length > 0 && !doNotAlert) {
                pronav.error({ title: "Required Information", msg: "The following fields are required:", errors: msgs });
            }

            return (msgs.length == 0);
        },

        saveNew: function (isEmployee) {

            var p = project.create.getData(isEmployee, true);
            project.create.showAsWorking();

            if (project.create.isValid(p, isEmployee)) {
                $.post('/project/save-new/', { data: JSON.stringify(p) }, function (o) {
                    if (o.errors instanceof Array && o.errors.length == 0) {
                        window.location = '/project/view/id/' + o.project_id;
                    } else {
                        project.create.showAsWorking(false);
                        if (o.errors instanceof Array) {
                            pronav.error({ title: "Required Information", msg: "The following fields are required:", errors: o.errors });
                        } else {
                            pronav.error({ title: "Uknown Error", msg: "An Unknown Error Occurred. Please Try Again." });
                        }
                    }

                    }, "json");
            } else {
                project.create.showAsWorking(false);
            }

        },

        doneForCorporationChanged: function () {

            var done_for_corporation = $("#done-for-corporation").val();

            if (done_for_corporation != 0){

                //reload the project team corporations.
                project.create.loadProjectTeamCorporations();

                $("#done-at-location").html('<option value="0">Loading...</option>');

                $.get('/project/get-corp-workgroups-users-locations', { done_for_corporation: done_for_corporation }, 

                    function (o) {
                        //Point of contact
                        $("#point-of-contact").html('<option value="0">&laquo; Select a Point of Contact &raquo;</option>' + o.users);

                        //Workgroup
                        $("#done-for-workgroup").html('<option value="0">&laquo; Select a Workgroup &raquo;</option>' + o.workgroups);

                        var workgroup_count = $("#done-for-workgroup option").length;
                        if (workgroup_count == 1) {
                            var msg = pronav.printf("A project cannot be created for this corporation because it has no workgroups.<br/><br/>Please first create a workgroup for this corporation and then return to create the project.<br /><br/><a href=\"/workgroup/create/id/%s\">Create Workgroup</a>",$("#done-for-corporation").val())
                            pronav.alert(msg,"No Workgroups Found"); 
                            return;  
                        } else if (workgroup_count == 2) {
                            $("#done-for-workgroup option:last").attr("selected", "selected");
                            $('#location-owner-autocomplete').focus();
                        } else if (workgroup_count > 2) {
                            $("#tr-done-for-workgroup").show().find('select').focus();
                        }

                        //Locaton Owner
                        $("#location-owner-autocomplete")
                        .val($("#done-for-corporation option:selected").text())
                        .css("color","black")
                        .trigger('blur');   

                        project.create.locationOwnerChanged(o.locations);

                    }, "json");
            } else {
                $("#tr-done-for-workgroup").hide();
                $("#done-for-workgroup option:gt(0)").remove();
                $("#location-owner-autocomplete").val('').trigger('blur').focus();
            }
        },

        locationOwnerChanged: function (locations) {         

            function setLocationOptions(lval){
                $("#done-at-location").html('<option value="0">&laquo; Select a Location &raquo;</option>' + lval);
                var location_count = $("#done-at-location option").length;
                if (location_count == 1) {
                    //No locations found, alert the user that they can't go on.     
                    var msg = pronav.printf("A project cannot be created for this location owner because it has no locations.<br/><br/>Please first create a location for this location owner and then return to create the project.<br/><br/><a style href=\"/location/create/id/%s\">Create Location</a>",$("#location-owner").val());
                    $("#tr_location").hide();
                    pronav.alert(msg, "No Locations Found");                   
                } else if (location_count == 2) {
                    //only one location found, select it but hide the select box. 
                    //The user will see only the full address in text below.
                    $("#tr_location").hide();
                    $("#done-at-location option:eq(1)").attr("selected","selected");
                    $("#done-at-location").change();                                        
                } else {
                    $("#tr_location").show();
                    $("#done-at-location").removeAttr("disabled");
                }
            } 

            var corporation_id = $("#location-owner").val();
            $("#td-address").html('&nbsp;');

            if (corporation_id != 0){
                $("#done-at-location").html('<option value="0">Loading...</option>');


                if (locations){
                    setLocationOptions(locations);
                } else {
                    $.get('/project/get-locations-for-corp', { corporation_id: corporation_id }, function (o) {
                        setLocationOptions(o);
                    });
                }
            } else {
                $("#tr_location").hide();
                $("#done-at-location option:gt(0)").remove();
            } 
        },

        locationChanged: function (addr) {

            function setAddress(addr_str){
                if ($("#done-at-location option").length == 2){
                    addr_str = pronav.printf("%s<br/>%s", 
                        $("#done-at-location option:selected").text(), addr_str);
                }
                $("#td-address").html(addr_str);
            }

            var location_id = $("#done-at-location").val();
            if (location_id == 0) {
                $("#td-address").html('&nbsp;');
            } else if (addr){
                //address already given, just set the val, no need to fetch it.
                setAddress(addr);
            } else {
                $.get('/location/address/', { id: location_id }, function (o) {
                    setAddress(o);
                });
            }
        },

        teamAssignedLoad: function () {

            var onList = $("#assigned-users").empty();

            //if the corporation select is a drop down, then break out the users by company. Otherwise just list users. 
            if ($("#corporation_select").is('select')) {
                var corps = {};
                onList.append('<option value="-1">Loading...</option>');
                for (var i in project.create.teamMembers) {
                    var user = project.create.teamMembers[i];
                    if (user.assigned != 1) { continue; }
                    if (!(corps[user.corporation] instanceof Array)) {
                        corps[user.corporation] = [];
                    }
                    corps[user.corporation].push(user);
                }

                corpKeys = pronav.getObjectKeys(corps, true);

                onList.empty();

                for (var i = 0; i < corpKeys.length; i++) {
                    var corp = corpKeys[i];
                    onList.append(pronav.printf('<optgroup class="dialog" label="%s"/>', corp));
                    var users = corps[corpKeys[i]];
                    users = pronav.sortByKey(users, 'name');
                    for (var u = 0; u < users.length; u++) {
                        var user = users[u];
                        onList.append(pronav.printf('<option value="%s">%s</option>', user.user_id, user.name));
                    }
                }
            } else {

                var assigned = [];
                for (var i in project.create.teamMembers) {
                    var user = project.create.teamMembers[i];
                    if (user.assigned == 1) {
                        assigned.push(user);
                    }
                }

                var sorted = pronav.sortByKey(assigned, 'name');
                onList.empty();
                for (var i = 0; i < sorted.length; i++) {
                    var user = sorted[i];
                    onList.append(pronav.printf('<option value="%s">%s</option>', user.user_id, user.name));
                }
            }
        },

        teamUnassignedLoad: function () {

            var offList = $("#available-users");
            offList.empty();
            offList.append('<option value="-1">Loading...</option>');

            var corp_id = $("#corporation_selected").val();

            var users = [];
            for (var i in project.create.teamMembers) {
                var user = project.create.teamMembers[i];
                if (user.corporation_id == corp_id && user.assigned == 0) {
                    users.push(user);
                }
            }
            users = pronav.sortByKey(users, 'name');
            offList.empty();
            for (var i = 0; i < users.length; i++) {
                var user = users[i];
                offList.append(pronav.printf('<option value="%s">%s</option>', user.user_id, user.name));
            }
        },

        addTeamMember: function () {
            $("#available-users").children(':selected').each(function (index, element) {
                var e = $(element);
                if (project.create.teamMembers[e.val()] != undefined) {
                    project.create.teamMembers[e.val()].assigned = 1;
                    e.remove();
                }
            });
            project.create.teamAssignedLoad();
            project.create.IE7Bug();
        },

        removeTeamMember: function () {
            $("#assigned-users").children(':selected').each(function (index, element) {
                var e = $(element);
                if (project.create.teamMembers[e.val()] != undefined) {
                    project.create.teamMembers[e.val()].assigned = 0;
                    e.remove();
                }
            });

            project.create.teamAssignedLoad();
            project.create.teamUnassignedLoad();
            project.create.IE7Bug();
        },

        teamCompanySelection: function () {

            //set the hidden values and reset the drop down.
            var corp_id = $("#corporation_select").val();
            $("#corporation_selected").val(corp_id);

            if (corp_id == -1) {
                $("#corporation_selected_display").text("Please Select A Customer From The List Above");
            } else {
                $("#corporation_selected_display").text($("#corporation_select").children(':selected').text());
            }

            $("#corporation_select").val(-1);

            //animate the company that you have changed to - since the drop-down box immediately reverts. 
            $("#corporation_selected_display").animate({ color: "red", backgroundColor: 'yellow', weight: 'bold' }, 500,
                function () {
                    $("#corporation_selected_display").animate({ color: "black", backgroundColor: 'white', weight: 'normal' }, 500);
            });

            //see if you have already fetched this corporation's users - if so, don't do it again.
            if ($.inArray(corp_id, project.create.fetchedCorps) == -1) {
                //you haven't so fetch them and load them.
                project.create.fetchCompanyUsers(corp_id);
            } else {
                //you have, so just load them.
                project.create.teamUnassignedLoad();
                project.create.teamAssignedLoad();
            }
        },

        fetchCompanyUsers: function (selected_corp) {
            ///<summary>Will fetch the users for the currently selected corporation and load them into the Unassigned Users list.
            ///It will also note that it has done so, so they won't be fetched again.</summary>

            var offList = $("#available-users");
            offList.empty().append('<option id="-1">Loading...</option>');
            $.post('/project/fetch-corp-users', { corporation_id: selected_corp }, function (o) {
                if (!(o instanceof Object) || o.state != 1 || !(o.users instanceof Object)) {
                    pronav.error({ msg: "An error occurred fetching this corporations users. Please try again." });
                } else {
                    for (var i in o.users) {
                        var user = o.users[i];
                        if (project.create.teamMembers[i] == undefined) {
                            if (user.pronav_access != '1'){
                                user.name = user.name + " *"
                            }
                            project.create.teamMembers[i] = user;
                        }
                    }
                    project.create.fetchedCorps.push(selected_corp);
                    project.create.teamUnassignedLoad();
                    project.create.teamAssignedLoad();
                }
                }, "json");
        },

        getSelectedTeamUsers: function () {
            ///<summary>Returns all the users assigned to the team as an array of user ids. This is used for submission of the form.</summary>
            var users = [];
            $.each(project.create.teamMembers, function (id, user) {
                if (user.assigned == 1) {
                    users.push(id);
                }
            });
            return users;
        },

        loadProjectTeamCorporations: function () {
            ///<summary>Loads the project team corporation select box. Its creates 2 divisions.
            ///It puts the main corporation and the selected corp in the first and all others in the second.
            ///It is fired when the user selects a company on the main project creation page.</summary>

            var corps = $("#done-for-corporation").children();
            var selected = $("#done-for-corporation").val();
            var teamCorps = $("#corporation_select");
            teamCorps.children().not(':first').remove();

            teamCorps.append('<optgroup class="dialog" label="Primary" />');

            teamCorps.append($("#done-for-corporation").find('[value=' + pronav.trim_id + ']').clone());

            if (selected != pronav.trim_id) {
                teamCorps.append($("#done-for-corporation").find('[value=' + selected + ']').clone());
            }

            teamCorps.append('<optgroup class="dialog" label="Third Party" />');
            corps.each(function (index, element) {
                var e = $(element);
                if (e.val() != pronav.trim_id && e.val() != selected) {
                    teamCorps.append(e.clone());
                }
            });
        },

        authorizationChanged: function () {
            var type = $("input[name=authorization-type]:checked").val();
            if (type == 5) {
                $(".po-rows td").css("color", "#000");
                $(".po_required").css('color', '#FF0000');
                $(".po-rows input").removeAttr("disabled");
                $("#po-number").focus();
            } else {
                $(".po-rows td").css("color", "#999");
                $(".po_required").css('color', '#999');
                $(".po-rows input").attr("disabled", "disabled");
            }
        }
    },

    loadEditScope: function (project_id) {
        var project_id = project_id || $("#project_id").val();
		
        $.get('/project/load-edit-scope', { id: project_id}, function (o) {
            $("#scope-edit").html(o).dialog({
                title: 'Edit Scope of Work',
                width: 500,
                modal: true,
                resizable: false,
                close: function(){$(this).dialog('destroy');},
                buttons: {
                    'Save': function () {

                        pronav.setDialogLoading();
                        var dialog = $(this);

                        var s = {};
                        s.scope = $("#edit_scope").val();
                        s.scope_internal = $("#edit_scope_internal").val();

                        $.post('/project/save-scope', 
                            { id: project_id, data: JSON.stringify(s) }, 
                            function (o) {
                                if (o.errors instanceof Array && o.errors.length == 0) {
                                    $("#scope").html(o.message);
                                    dialog.dialog('close');
                                } else {
                                    pronav.setDialogErrors(o.errors);
                                }
                            }, 
                            "json");
                    },
                    'Cancel': function () { $(this).dialog('close'); }
                }
            });
        });
    },
	loadEditMaterialLocation: function (project_id) {
        var project_id = project_id || $("#project_id").val();
		$('#material-location').hide('slow');
        $.get('/project/load-edit-material-location', { id: project_id}, function (o) {
			$("#editMaterialForm").html(o).show('fast');
			$('#saveMaterialLocation').show('slow');
			$('#cancelMaterialLocation').show('slow');
			$('#loadEditMaterialLocation').hide('slow');
			$('#edit_material_location').autogrow();
		});
    },
	saveMaterialLocation: function (project_id) {
        var project_id = project_id || $("#project_id").val();
		
		var s = {};
		s.material_location = $("#edit_material_location").val();
		
		$.post('/project/save-material-location', 
			{ id: project_id, data: JSON.stringify(s) }, 
			function (o) {
				if (o.errors instanceof Array && o.errors.length == 0) {
					$("#material-location").html(o.message);
					$("#editMaterialForm").html('');
					$('#material-location').show();
					
					$('#saveMaterialLocation').hide();
			$('#cancelMaterialLocation').hide();
			$('#loadEditMaterialLocation').show();
			
				} else {
					pronav.setDialogErrors(o.errors);
				}
			}, 
			"json");
	
	

    },
	cancelMaterialLocation: function (project_id) {
		$("#editMaterialForm").html('');
		$('#material-location').show();
		$('#saveMaterialLocation').hide();
		$('#cancelMaterialLocation').hide();
		$('#loadEditMaterialLocation').show();
	},

    edit: {
        doneForCorpChanged: function () {

            var done_for_corporation = $("#done-for-corporation").val();
            var dfw = $("#done-for-workgroup");
            var poc = $("#point-of-contact"); 
            dfw.attr("disabled","disabled");
            poc.attr("disabled","disabled");
            dfw.html('<option value="0">Loading...</option>');
            poc.html('<option value="0">Loading...</option>');

            $.get('/project/get-corp-workgroups-users-locations', { done_for_corporation: done_for_corporation }, 
                function (o) {
                    dfw = $("#done-for-workgroup");
                    dfw.html(o.workgroups);
                    if (dfw.children().length > 1){
                        dfw.prepend('<option value="0">&laquo; Please Select A Workgroup &raquo;</option>').val(0);
                    }                                                
                    poc.html('<option value="0">&laquo; Select a Point of Contact &raquo;</option>').append(o.users);
                    dfw.removeAttr("disabled");
                    poc.removeAttr("disabled");                    
                },
                "json");
        },

        doneAtCorpChanged: function () {
            var corporation_id = $("#done-at-corporation").val();
            var dal = $("#done-at-location");
            dal.html('<option value="0">Loading...</option>').attr("disabled","true");
            $.get('/project/get-locations-for-corp', { corporation_id: corporation_id }, function (o) {
                dal.html(o);
                if (dal.children().length > 1){
                    dal.prepend('<option value="0">&laquo; Select a Location &raquo;</option>').val(0);
                }
                dal.removeAttr("disabled");
            });
        }
    },

    loadCreateLocation: function () {
        var corporation_id = $("#done-at-corporation").val();
        var corporation_name = $("#done-at-corporation option:selected").text();
        if (corporation_id > 0) {

            $.get('/project/load-add-location', { corporation_name: corporation_name }, function (o) {
                $("#add-loc").html(o).dialog({
                    title: 'Adding New Location for ' + corporation_name,
                    width: 450,
                    resizable: false,
                    modal: true,
                    close: function(){$(this).dialog('destroy');},
                    buttons: {
                        'Save': function () {
                            if ($("input[name=loc-save-type]:checked").length == 0) {
                                $("#add-loc-err").html('<ul><li>Please select ownership type.</li></ul>');
                                return;
                            }
                            var l = {};
                            l.corporation_id = corporation_id;
                            l.name = $("#loc-name").val();
                            l.address1 = $("#loc-address1").val();
                            l.address2 = $("#loc-address2").val();
                            l.city = $("#loc-city").val();
                            l.state = $("#loc-state").val();
                            l.zip = $("#loc-zip").val();
                            $.post('/location/save-new', { data: JSON.stringify(l) }, function (o) {
                                if (o.newId > 0) {
                                    $('#location-id').append('<option value="' + o.newId + '">' + l.name + '</option>');
                                    $("#location-id").val(o.newId);
                                    $("#add-loc").dialog('close');
                                } else {
                                    project.writeOutError("add-loc-err", o);
                                }
                                }, "json");
                        },
                        'Close': function () {
                            $(this).dialog('close');
                        }
                    }
                });
            });
        } else {
            alert('Please select a corporation.');
        }
    },

    writeOutError: function (sID, o) {
        $("#" + sID).html('');
        var oU = $('<ul></ul>');
        if (o.errors.length == 0) {
            oU.append("<li>An Unknown Error Occurred. Please Try Again.</li>");
        } else {
            for (var i = 0; i < o.errors.length; i++) {
                var err = "<li>" + o.errors[i] + "</li>";
                oU.append(err);
            }
        }
        $("#" + sID).append(oU);
    },

    writeOutErrorDlg: function (sID, o) {
        project.writeOutError(sID, o);
        $("#" + sID).dialog({
            title: "Required Information",
            modal: true,
            resizable: false,
            width: 400,
            close: function(){$(this).dialog('destroy');},
            buttons: { OK: function () { $(this).dialog('close'); } }
        });
    },

    loadCreateCorp: function (sID) {
        var corporation_name = $("#" + sID + "-search").val();

        $.get('/project/load-add-corporation', { corporation_name: corporation_name }, function (o) {
            $("#add-corp").html(o).dialog({
                title: 'Add a New Corporation',
                width: 450,
                modal: true,
                resizable: false,
                close: function(){$(this).dialog('destroy');},
                buttons: {
                    'Save': function () {
                        project.saveCorporation(sID);
                    },
                    'Cancel': function () { $(this).dialog('close'); }
                }
            });
            project.attachAutoCompleteToCorp("new-corporation-name", sID);
        });
    },

    attachAutoCompleteToCorp: function (sInput, sID) {
        $("#" + sInput).autocomplete({
            source: '/corporation/search',
            minLength: 2,
            messages: {
                noResults: "",
                results: function() {}
            },
            select: function (event, ui) {
                $("#" + sID).val(ui.item.id);
                if (sID == 'done-for-corporation')
                    project.doneForCorpChanged();
                else if (sID == 'done-at-corporation')
                    project.doneAtCorpChanged();
                $("#add-corp").dialog('close');
                $("#search-corp").dialog('close');
            }
        }).focus();
    },

    saveCorporation: function (sID) {
        var c = {};
        c.name = $("#new-corporation-name").val();
        c.industry = $("#new-industry-id").val();
        c.internal_note = $("#new-note").val();
        $.post('/corporation/save-new', { data: JSON.stringify(c) }, function (o) {
            if (o.newId > 0) {
                $("#done-for-corporation").append('<option value="' + o.newId + '">' + c.name + '</option>');
                $("#done-at-corporation").append('<option value="' + o.newId + '">' + c.name + '</option>');
                $('#' + sID).val(o.newId);
                if (sID == 'done-for-corporation') {
                    project.doneForCorpChanged();
                } else if (sID == 'done-at-corporation') {
                    project.doneAtCorpChanged();
                    project.loadCreateLocation();
                }
                $("#add-corp").dialog('close');
            } else {
                project.writeOutError("add-corp-err", o);
            }
            }, "json");
    },

    loadSearchCorp: function (sID) {
        var sTitle = sID == 'done-for-corporation' ? 'Bill To Corporation' : 'End User Corporation';
        $("#search-corp-name").val('');
        $("#search-corp").dialog({
            title: 'Search for ' + sTitle,
            width: 450,
            modal: true,
            resizable: false,
            close: function(){$(this).dialog('destroy');},
            buttons: {
                'Close': function () { $(this).dialog('close'); }
            }
        });
        project.attachAutoCompleteToCorp("search-corp-name", sID);
    },

    /* Used by the /project/index/ page */
    list: {

        _init : function(){       

            $("#btn-show, #btn-clear, #btn-default, #btn-load").button();

            $("#done-for-corporation-autocomplete").autocompletePlus({
                list_field : '#done-for-corporation',
                label : 'Search for Customers',
                source : '/corporation/search'  
            });  

            $('#done-for-corporation').on('change', function(){
                project.list.doneForCorpChanged(project.list.showCounts)
            });

            $("#location-owner-autocomplete").autocompletePlus({
                list_field : '#location-owner',
                label : 'Search Location Owners',
                source : '/corporation/search'                 
            });

            $('#location-owner').on('change', function(){
                project.list.locationOwnerChanged(function(){project.list.showCounts()});    
            });

            $('#done-at-location, #done-by-workgroup, #done-for-workgroup, #my-watch-list, #point_of_contact').on('change', function(){project.list.showCounts()});
            $('#btn-show').on('click', project.list.toggleCounts);
            $('#btn-clear').on('click', project.list.clearFilter);
            $('#btn-load').on('click', reloadPage);
            $('#btn_save_as_default').on('click', project.list.saveFilter);
            $("#btn-show").focus();       

            // Back Button Hash Functions 
            //set up the handler to store the state with each project link click. 
            //state will be stored just prior to going to link. 
            $("#content").on("click",".project-tbl a", project.list.setHash); 

            if (pronav.trim(window.location.hash).length > 0) {
                project.list.getStateFromHash();  
            } else {     

                //set the display of the auto-complete fields to that of the hidden combo boxes. 
                var dfc = $("#done-for-corporation");
                var dflo = $("#location-owner");

                if (dfc.val() != 0){
                    $("#done-for-corporation").val(dfc.val());
                    $("#done-for-corporation-autocomplete").val($("#done-for-corporation option:selected").text()).css("color", "#000000");
                }

                if (dflo.val() != 0){
                    $("#location-owner").val(dflo.val())
                    $("#location-owner-autocomplete").val($("#location-owner option:selected").text()).css("color","#000000");
                }

                //fetch the projects now, based on the data provided. 
                project.list.showCounts();    
            }   
        },

        setHash : function(e){

            var loc = pronav.printf("%s%s",location.origin, location.pathname);

            var hash = {
                c  : $("#done-for-corporation").length ? $("#done-for-corporation").val() : 0,
                wd : $("#done-for-workgroup").length ? $("#done-for-workgroup").val() : 0,
                lo : $("#location-owner").length ? $("#location-owner").val() : 0,
                l  : $("#done-at-location").length ? $("#done-at-location").val() : 0,
                wb : $("#done-by-workgroup").length ? $("#done-by-workgroup").val() : 0,
                pc : $("#point_of_contact").length ? $("#point_of_contact").val() : 0,
                wl : $("#my-watch-list:checked").length
            };                   

            $(".project-list-stage").each(function(i,e){
                var stg = $(e);
                hash[stg.attr('id')] = stg.is(":visible") ? 1 : 0;
            });

            var hashed_url = pronav.printf("%s#%s",location.href, pronav.serialize(hash));
            window.location.hash = ("#"+pronav.serialize(hash));            
        },

        getStateFromHash : function(){            

            if (pronav.trim(location.hash).length > 0){

                //The following is a long chain of ajax calls that happen in sequence to avoid a race condition.
                var hash = pronav.objectify(location.hash.substring(1));

                //Do the easy ones that require no callbacks. 
                $("#done-by-workgroup").val(hash.wb);
                if (hash.wl == 1){
                    $("#my-watch-list").attr("checked","checked");
                } else {
                    $("#my-watch-list").removeAttr("checked");
                }
                $('#point_of_contact').val(hash.pc);

                var tasks_finished = 0;

                if ($("#done-for-corporation").length == 0){
                    //client page, just load location selection. 
                    $("#done-at-location").val(hash.l);
                    //Now all fields are loaded, get show counts. 
                    project.list.showCounts(null, function(){

                        //once the counts are loaded, iterate through and open the ones that should be opened. 
                        $(".project-list-stage").each(function(i,e){
                            var stg = $(e);
                            var id = stg.attr('id');
                            if (hash[id] == "1"){
                                stg.prev().find('a').click();
                            }   
                        });    
                    });
                } else { 
                    //Tri-M Employee setup.
                    //Set corporation selection. 
                    $("#done-for-corporation").val(hash.c);
                    //only set the auto-complete if an actual value is selected.
                    if (hash.c != 0){
                        $("#done-for-corporation-autocomplete")
                        .val($("#done-for-corporation option:selected").text())
                        .css("color","#000000");                     
                    }

                    //Get corporaton workgorups then move onto locations. 
                    project.list.doneForCorpChanged(function(){

                        //only continue once the values are loaded.  
                        var dfw = $("#done-for-workgroup");
                        dfw.val(hash.wd);
                        if (hash.wd == 0){
                            dfw.attr("disabled","disabled");    
                        } else {
                            dfw.removeAttr("disabled");
                        }

                        //Now set location owner
                        $("#location-owner").val(hash.lo);
                        if (hash.lo != 0){
                            $("#location-owner-autocomplete")
                            .val($("#location-owner option:selected").text())
                            .css("color","#000000"); //HACK
                            //Should inline label blur event but this will cause project.getcounts() call which I don't want.                         
                        }

                        //get location owner locations, once you have them set the values. 
                        project.list.locationOwnerChanged(function(){
                            //only try to set the lists value once the list items have been fetched. 
                            var dal = $("#done-at-location");
                            dal.val(hash.l);                        
                            if (hash.l == 0){
                                dal.attr("disabled","disabled");    
                            } else {
                                dal.removeAttr("disabled");
                            }

                            //Now all fields are loaded, get show counts. 
                            project.list.showCounts(null, function(){

                                //once the counts are loaded, iterate through and open the ones that should be opened. 
                                $(".project-list-stage").each(function(i,e){
                                    var stg = $(e);
                                    var id = stg.attr('id');
                                    if (hash[id] == "1"){
                                        stg.prev().find('a').click();
                                    }   
                                });    
                            });
                        });    
                    });    
                }
            }    
        },

        doneForCorpChanged : function (callback) {
            var done_for_corporation = $("#done-for-corporation").val();
            $("#done-for-workgroup").html('<option value="0">Loading...</option>');
            $.get('/project/get-corp-workgroups-users-locations', { done_for_corporation: done_for_corporation }, 
                function (o) {
                    var dfw = $("#done-for-workgroup");
                    dfw.html('<option value="0">&laquo; All Workgroups &raquo;</option>');    
                    dfw.append(o.workgroups);
                    dfw.removeAttr('disabled');
                    if (typeof callback == "function"){
                        callback();
                    }
                }, "json");
        },

        locationOwnerChanged : function (callback) {
            var corporation_id = $("#location-owner").val();
            $("#done-at-location").html('<option value="0">Loading...</option>');
            $.get('/project/get-locations-for-corp', { corporation_id: corporation_id }, function (o) {
                var dal = $("#done-at-location");
                dal.html('<option value="0">&laquo; All Locations &raquo;</option>' + o);
                dal.removeAttr('disabled');
                if (typeof callback == "function"){
                    callback();
                }
            });
        },

        showCounts : function (vals, callback) {

            //you can provide your own values if you don't want to use the form values. 
            //The 'back' button support uses this b/c not all lists are loaded in time. 
            vals = vals || project.list.getFilter();
            $("#toggle-state").val(0);
            $("#content").html('<div style="margin:10px;"><img src="/images/ajax-loader-long.gif" /></div>');

            $.post('/project/project-counts', { data: JSON.stringify(vals) }, function (o) {

                $("#content").html(o.html);

                //run the callback if one is present. 
                if (typeof callback == "function"){
                    callback();
                }

                }, "json");
        },

        showProjects: function () {
            var f = project.list.getFilter();
            $("#content").html('<div style="margin:10px;">Loading ... <br/><img src="/images/ajax-loader-long.gif" /></div>');
            $.post('/project/list-projects', { data: JSON.stringify(f) }, function (o) {
                $("#content").html(o);
                $(".project-tbl").tablesorter({ sortList: [[0, 0]], widgets: ['zebra'] }).addClass('tablesorter');
            });
        },

        getFilter: function () {
            var f = {};
            f.done_for_corporation = $("#done-for-corporation").val();
            f.done_for_workgroup = $("#done-for-workgroup").val();
            f.done_at_location = $("#done-at-location").val();
            f.location_owner = $("#location-owner").val();
            f.done_by_workgroup = $("#done-by-workgroup").val();
            f.point_of_contact = ($("#point_of_contact").length == 0 ? 0 : $("#point_of_contact").val());
            f.keywords = $("#keywords").val();
            f.my_watch_list = $("#my-watch-list").is(":checked") ? 1 : 0;
            return f;
        },

        clearFilter: function () {

            $("#done-for-corporation, #location-owner, #done-by-workgroup, #point_of_contact, #done-for-workgroup, #done-at-location").val(0);
            $("#done-for-corporation-autocomplete, #location-owner-autocomplete").val('').blur();
            $("#my-watch-list").attr("checked", false);

            if ($('#location-owner').length > 0) {
                $("#done-for-workgroup").html('<option value="0">&laquo; All Workgroups &raquo;</option>');
                $("#done-at-location").html('<option value="0">&laquo; All Locations &raquo;</option>');
            }

            $("#content").html('');
            project.list.showCounts();
        },

        toggleProjectStage: function (stage_id) {
            var oDiv = $("div#stage-" + stage_id);
            var oA = $("#stage-" + stage_id + "-a");
            if (oDiv.html() == "") {
                var f = project.list.getFilter();
                f.stage_id = stage_id;
                oDiv.html('<div style="margin-top:5px;"><img src="/images/ajax-loader-long.gif" /></div>').show();
                $.post('/project/list-projects', { data: JSON.stringify(f) }, function (o) {
                    oDiv.html(o);
                    if ($(o).find("tr").length < 500) {
                        $("#tbl-stage-" + stage_id).tablesorter({ sortList: [[0, 0]], widgets: ['zebra'] });
                    }
                });
            } else {
                if (oDiv.is(":visible")) {
                    oDiv.hide();
                } else {
                    oDiv.show();
                }
            }
        },

        saveFilter: function () {
            var f = project.list.getFilter();
            $("#filter_saving").show();
            $.post('/project/save-filter', { data: JSON.stringify(f) }, function (o) {
                //pronav.alert('Your settings have been saved.', 'Project Filter Default View');
                $("#filter_saving").hide();
                $("#filter_saved").show().fadeOut(2000);
            });
        },

        toggleCounts: function () {
            $("#project-filter-button-status").html('Please wait...');
            $("a[class^='stage-has-projects']").each(function (i) {
                var stage_id = $(this).attr("class").replace("stage-has-projects-", "");
                if (stage_id != 9 && stage_id != 8)
                    project.list.toggleProjectStage(stage_id);
            });
            $("#project-filter-button-status").html('');
            if ($("#toggle-state").val() == '0') {
                $(".project-list-stage").show();
                $("#toggle-state").val(1);
            } else {
                $(".project-list-stage").hide();
                $("#toggle-state").val(0);
            }
        }

    },

    loadProjectInfoEdit: function (project_id) {
        project_id = project_id || $("#project_id").val();
        $.get('/project/edit-project-info', { id: project_id}, function (o) {
            $("#project-info-edit").html(o).dialog({
                title: 'Edit Project Info',
                width: 600,
                modal: true,
                resizable: false,
                close: function(){$(this).dialog('destroy');},
                buttons: {
                    'Save': function () {

                        var p = {};
                        p.title = $("#project-title").val();
                        p.stage_id = $("#stage-id").val();
                        p.stage_comment = $("#stage-comment").val();
                        p.done_for_corporation = $("#done-for-corporation").val();
                        p.done_for_workgroup = $("#done-for-workgroup").val();
                        p.done_at_location = $("#done-at-location").val();
                        p.location_owner = $("#location-owner").val();
                        p.done_by_workgroup = $("#done-by-workgroup").val();
                        p.point_of_contact = $("#point-of-contact").val();
                        p.requested_by = $("#requested-by").val();
                        p.schedule_not_before = $("#edit_schedule_not_before").val();
                        p.schedule_required_by = $("#edit_schedule_required_by").val();

                        if ($("#ref-number").length == 1){
                            p.ref_no = $("#ref-number").val();
                        }

                        pronav.setDialogLoading();
                        $.post('/project/update-project-info', { id: project_id, data: JSON.stringify(p) },                             function (o) {
                            if (typeof o == 'object') {
                                if (o.errors instanceof Array && o.errors.length > 0) {
                                    pronav.setDialogErrors(o.errors);
                                } else {
                                    $("#project-info-edit").dialog('close');
                                    window.location = '/project/view/id/' + project_id;
                                }
                            } else {
                                pronav.setDialogErrors(null, 'An Unknown Error Occurred. Please Try Again.');
                            }
                            }, "json");

                    },
                    'Cancel': function () { $(this).dialog('close'); }
                }
            });
            $("#edit_schedule_required_by, #edit_schedule_not_before").datepicker({
                changeMonth: true,
                dateFormat: 'm/d/y'
            });
        });        
    },

    removeFromWatchList: function (project_id) {
        $.post('/dashboard/remove-from-watch-list', { project_id: project_id, user_action: 0 }, function (o) {
            $("#my-projects").html(o);
        });
    },

    loadClientStage: function (from_stage, stage_id, oLink, project_id, resub) {
        var project_id = project_id || $("#project_id").val();
        var title = $(oLink).html();

        $("#project-info-edit").html('<div id="dg-error" style="color:red;"></div>');
        var to_stage = stage_id;

        $.get('/project/load-client-stage', { id: project_id, to_stage: to_stage, from_stage: from_stage }, function (o) {
            $("#project-info-edit").append(o).dialog({
                title: title,
                width: 'auto',
                modal: true,
                resizable: false,
                close: function(){
                    $('#stage_change_dialog').remove();
                    $(this).dialog('destroy');
                },
                open: function () {                                                                    
                    var bill_type = $('#edit_bill_type');
                    if (bill_type.length == 1 && bill_type.val() != -1){
                        $('#edit_acct_project_value').focus();        
                    }                                                                                  
                },
                buttons: {
                    'OK': function (GoToFileUpload) {

                        pronav.setDialogLoading();
                        var dialog = $(this);
                        
                        var d = {
                            stage_id : stage_id  
                        };          

                        //all potential fields that could be in the form.
                        //Only submit them back if they actually exist, otherwise values in the DB will get wiped.  
                        //field ID, field label, if it is a checkbox.
                        var flds = [
                            ['input[name=authorize]:checked','authorization_type'],
                            ['#edit_done_for_corporation','done_for_corporation'],
                            ['#edit_done_for_workgroup','done_for_workgroup'],
                            ['#edit_point_of_contact','point_of_contact'],                            
                            ['#comment','comment'],
                            ['#po-number','po_number'],
                            ['#po-date','po_date'],
                            ['#po-amount','po_amount'],
                            ['#edit_job_no','job_no'],
                            ['#edit_bill_type','bill_type'],
                            ['#edit_acct_project_value','acct_project_value'],
                            ['#edit_hold_stage_override','edit_hold_stage_override'],
                            ['#open_project_acct_project_manager','acct_project_manager'],
                            ['#edit_acct_estimated_cost','acct_estimated_cost'],
                            ['#edit_acct_prevailing_wage_yes','acct_prevailing_wage'],
                            ['#edit_acct_tax_exempt_yes','acct_tax_exempt'],
                            ['#edit_acct_invoice_type','acct_invoice_type'],
                            ['#edit_acct_retainage','acct_retainage'],
                            ['#edit_acct_billing_date','acct_billing_date'],
                            ['#edit_acct_billing_address1','acct_billing_address1'],
                            ['#edit_acct_billing_address2','acct_billing_address2'],
                            ['#edit_acct_billing_city','acct_billing_city'],
                            ['#edit_acct_billing_state','acct_billing_state'],
                            ['#edit_acct_billing_zip','acct_billing_zip'],
                            ['#edit_acct_billing_notes','acct_billing_notes'],
                            ['#edit_acct_cert_of_ins_req_yes','acct_cert_of_ins_req'],
                            ['#edit_schedule_estimated_start', 'schedule_estimated_start'],
                            ['#edit_schedule_estimated_end', 'schedule_estimated_end'],
                            ['#edit_acct_ocip_yes', 'acct_ocip'],
                            ['#edit_acct_performance_bond_req_yes', 'acct_performance_bond_req'],                            
                            ['#edit_acct_permit_req_yes', 'acct_permit_req'],
                            ['#edit_po_number', 'po_number'],
                            ['#edit_po_date', 'po_date'],
                            ['#edit_po_amount', 'po_amount'],                            
                            ['#edit_acct_invoice_type', 'acct_invoice_type'],
                            ['#edit_acct_billing_date', 'acct_billing_date'],
                            ['#edit_acct_retainage', 'acct_retainage'],
                            ['#edit_acct_cert_of_ins_req_yes:checked', 'acct_cert_of_ins_req']                            
                        ];                             

                        for (var i = 0; i < flds.length; i++){
                            var fld = flds[i];
                            var jfld = $(fld[0]);
                            if (jfld.length == 1){
                                if (jfld.is('[type=radio]')){
                                    d[fld[1]] = jfld.is(':checked') ? 1 : 0;
                                } else {
                                    d[fld[1]] = $.trim(jfld.val());
                                }
                            }
                        }  

                        if (d.done_for_corporation != undefined && d.done_for_corporation < 1){
                            pronav.setDialogErrors(null, 'A Customer Selection is Required.');
                            return;
                        }

                        if (d.done_for_workgroup != undefined && d.done_for_workgroup < 1){
                            pronav.setDialogErrors(null, 'A Workgroup Selection is Required.');
                            return;
                        }

                        if ((stage_id == project.stage.HOLD || stage_id == project.stage.CANCELLED) && d.comment == '') {
                            pronav.setDialogErrors(null, 'A Comment is Required.');
                            return;
                        }          

                        if (stage_id == project.stage.AUTHORIZED && $("input[name=authorize]").length) {
                            if ($("input[name=authorize]:checked").length == 0) {
                                pronav.setDialogErrors(null, 'Please Select Your Authorization Type.');
                                return;
                            }
                        }

                        if ((to_stage == project.stage.PROPOSED  || to_stage == project.stage.AUTHORIZED) && d.bill_type == -1) {
                            pronav.setDialogErrors(null, 'A Proposal Type is Required.');
                            return;
                        } 

                        d.resubmit = resub;

                        $.post('/project/update-stage', {
                            id: project_id,
                            to_stage: stage_id,
                            from_stage: from_stage,
                            data: JSON.stringify(d)
                            },
                            function (o) {

                                var reload = function(stage_id){
                                    if (stage_id == project.stage.CANCELLED) {
                                        window.location = '/project/index';
                                    } else {
                                        window.location.reload();
                                    }   
                                }

                                if (o && o.status == 1){
                                    
                                    dialog.dialog('close');
                                    
                                    if (o.offer_save_billing){
                                    
                                        o.offer_save_billing = eval(o.offer_save_billing);
                                        
                                        var msg = 'The following billing information fields were updated and differ from the customers record:<br/><ul>';
                                        $(o.offer_save_billing).each(function(i,e){
                                            msg += '<li>'+e[2]+'</li>';
                                        });
                                        msg+='</ul>Would you like to update the customers billing information with these?';                                        
                                        
                                        pronav.confirm({
                                            title : 'Corporation Billing Information',
                                            msg : msg,
                                            confirmCallback : function(){
                                                $.post('/project/save-project-billing-to-corp', {project_id : project_id, flds : o.offer_save_billing}, function(){
                                                    reload(stage_id);
                                                });
                                            },
                                            cancelCallback : function(){
                                                reload(stage_id);        
                                            },
                                            confirm : 'Yes',
                                            cancel : 'No'
                                        });
                                    } else {
                                        reload(stage_id);
                                    }

                                } else {
                                    pronav.setDialogErrors(o.errors);
                                    return;
                                }              

                            }, "json");

                    },
                    'Cancel': function () { $(this).dialog('close'); }
                }
            });
        });
    },

    loadClientStageCorporationChange: function(evt){
        
        var corp_id = $("#edit_done_for_corporation").val();
        $('#edit_done_for_workgroup option:gt(0), #edit_point_of_contact option:gt(0)').remove()
        $('#edit_done_for_workgroup option:eq(0), #edit_point_of_contact option:eq(0)').html('Loading ...');


        $.get('/project/get-corp-workgroups-users-locations', {done_for_corporation : corp_id}, function(o){
            $('#edit_done_for_workgroup option:eq(0)').html('&laquo; Select a Workgroup &raquo;');    
            $('#edit_point_of_contact option:eq(0)').html('&laquo; Select a Point of Contact &raquo;');
            $('#edit_point_of_contact').append(o.users);

            $('#edit_acct_billing_address1').val(o.billing.billing_address1);
            $('#edit_acct_billing_address2').val(o.billing.billing_address2);
            $('#edit_acct_billing_city').val(o.billing.billing_city);
            $('#edit_acct_billing_state').val(o.billing.billing_state);
            $('#edit_acct_billing_zip').val(o.billing.billing_zip);
            $('#edit_acct_billing_contact').val(o.billing.billing_contact);
            $('#edit_acct_billing_phone').val(o.billing.billing_phone);
            $('#edit_acct_billing_notes').val(o.billing.billing_notes);                

            $('#edit_done_for_workgroup').append(o.workgroups); 
            if ($('#edit_done_for_workgroup option').length == 2){
                $('#edit_done_for_workgroup').val($('#edit_done_for_workgroup option:eq(1)').val());                
            } else {                
                var orig = $('#edit_done_for_workgroup').data('original');
                if ($('#edit_done_for_workgroup option[value='+orig+']').length == 1){
                    $('#edit_done_for_workgroup').val(orig);
                }
            }
            }, "json");
    },

    promoteToProject: function(project_id){
        pronav.confirm({
            msg: 'Are you sure you want to promote this change order to a project?',
            confirmCallback: function(){
                $.post('/project/promote-to-project', {id: project_id}, function(o){
                    if(o && o.state == 1){
                        window.location = '/project/view/id/' + project_id;
                    }else{
                        pronav.error({ msg: "An Error Occurred. Please Try Again." });
                    }
                    }, "json");
            }          
        });  
    },

    loadOpenProjectsAtLocation: function (done_at_location, project_id, type) {
        var title = type == "active" ? "Active" : "Open";
        $.get('/project/other-projects', {done_at_location: done_at_location, project_id: project_id, type: type}, function(s){
            $("#other-projects").html(s).dialog({
                title: title + " Projects at this Location",
                resizable: false,
                width: 900,
                position:['center', 20],
                modal: true,
                open: function(event, ui){
                    $(".other-projects-lnk").click(function(){
                        $("#open-projects tbody tr").css("background-color", "#fff");
                        var $a = $(this);
                        var other_id = $a.text(); 
                        if(other_id != "0"){
                            $a.parent().parent().css("background-color", "#FFF380");
                            $.get('/project/team-members', {project_id: other_id}, function(o){
                                $("#open-projects-team").html(o);                                 
                            });   
                        }else{
                            $("#open-projects-team").html('');
                        }
                    }); 
                    $(".other-projects-lnk")[0].click(); 
                },
                buttons: {
                    'Close': function(){$(this).dialog('destroy');}
                }
            });
        });
    },

    loadEditHistory: function(project_id){
        alert('This feature is under development.');
    },

    projectStageEditor: function(project_id, callback_save){
        $.get('/project/project-stage-editor', {id: project_id}, function(o){
            $("#dgChangeOrderEdit").html(o).dialog({
                title: 'Edit Stage',
                width: 650,
                modal: true,
                resizable: false,
                buttons: {
                    'Save': function(){
                        if(typeof callback_save === "function" && callback_save != null){
                            callback_save();
                        }
                    },
                    'Cancel': function(){ $(this).dialog('close');}
                }, 
                close: function(){ $(this).dialog('destroy');}
            });
        });        
    },

    initReloadableProjectTabs: function(project_id){

        $("#task-update").click(function(){
            var loadId = pronav.setAsLoading("project_task_section");
            $("#project_task_section").load('/task/update',{collection_id: project_id},
                function(){
                    $("#task-std-menu").hide();
                    $("#task-edit-menu").show();
                    pronav.removeLoading(loadId);


                    //retain for later comparison
                    pronav.project.taskOriginalVals[0] = {
                        note: $.trim($("#edit_status_comment").val()),
                        progress: parseInt($("#edit_original_status_percent").val())
                    };

                    $('#task-tbl input:hidden[name=task_id]').each(function () {
                        var id = $(this);
                        var row = id.closest('tr');
                        var task = {};
                        task.note = row.find('textarea').val();
                        task.progress = row.find('select').val();  
                        pronav.project.taskOriginalVals[id.val()] = task;
                    }); 
                }
            );
        }); 

        $(".note-toggle-all").click(function(){
            pronav.note.checkIt = $(this);
            pronav.note.toggleAllNotes($(this), $(this).parent().parent().parent().attr('id'));
        });
        $("#createNote").click(function(){
            //store the section id so you can find where to put this new note. 
            pronav.note.noteUpdate = $(this).parent().parent().parent().attr('id');

            //get the note form. 
            pronav.note.getNote(-1,5,parseInt(project_id)); 
        });

        //since I am adding new note rows dynamically, I need this function to be external
        //so I could call it when notes are added or updated. 
        pronav.note.addToggleHandler();
    },

    calcGrossMarginAndMarkup: function(value, cost){

        value = value ? pronav.fromCurrency(value) : null;
        cost = cost ? pronav.fromCurrency(cost) : null;

        if (pronav.isNumber(value) && pronav.isNumber(cost) && (value > 0 || cost > 0)){

            var diff = (value-cost);
            var margin = (diff/value);    
            var margin_formatted = pronav.toPercentage(pronav.round((margin*100),2));
            margin_formatted = margin >= 0 ? margin_formatted : pronav.printf('<span class="red">%s</span>', margin_formatted);

            var markup = (value/cost);
            var markup_formatted = pronav.formatNumberAsString(pronav.round(markup,2),2);
            return pronav.printf('%s / %s', margin_formatted, markup_formatted);

        } else {
            return '';
        } 
    }
};

$(function () {
    project.init();
});