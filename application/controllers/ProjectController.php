<?php
class ProjectController extends Zend_Controller_Action
{
    public function init()
    {
	
        ProNav_Auth::authenticate();
        $project_id = $this->_getParam('id',-1);
        $workgroup_id = $this->_getParam('workgroup_id',-1);
        $corporation_id = $this->_getParam('corporation_id',-1);
        /* NOTE updating the overall project status and its tasks is handled in the TaskController */

        switch ($this->getRequest()->getActionName())
        {
            case 'index':
            case 'global-search';
            case 'create':
            case 'search-results':
            case 'list-projects':
            case 'save-filter':  
            case 'save-new':             
            case 'get-corp-workgroups-users-locations':
            case 'get-corp-workgroups-locations':
            case 'get-locations-for-corp':
            case 'project-counts':
            case 'get-initial-project-team-members':
            case 'fetch-corp-users':
                //allow anyone
                break;
            case 'view':
            case 'assign-user':  
            case 'load-client-stage':
            case 'update-stage':
            case 'assign-project-team': 
            case 'edit-project-team':  
            case 'get-corporation-unassigned-users':
            case 'get-corporation-assigned-users':
            case 'refresh-change-orders':
                if (!ProNav_Auth::hasAccessToProjectID($project_id)){
                    ProNav_Auth::unauthorized();
                }
                break;                
            case 'edit-completed-work':
            case 'commit-completed-work':
                //If you are an employee and have access to the project you may edit completed work IF
                //it is not closed or you have a special permission. 
                $project =  Application_Model_Projects::GetProjectInfo($project_id);
                if (ProNav_Auth::isEmployee() && ProNav_Auth::hasAccessToProjectID($project_id)){
                    if (!(!$project->isClosed() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_PROGRESS_COMPLETED_WORK_EDIT))){
                        ProNav_Auth::unauthorized();
                    }
                } else {
                    ProNav_Auth::unauthorized();
                }                               
                break;
            case 'load-edit-scope':
			case 'load-edit-material-location':
			case 'save-material-location':
            case 'save-scope':
                if(!(ProNav_Auth::hasAccessToProjectID($project_id) && ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SCOPE_EDIT))){
                    ProNav_Auth::unauthorized();
                }
                break;
            case 'edit-accounting':
            case 'commit-accounting':
                if(!(ProNav_Auth::hasAccessToProjectID($project_id) && ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_ACCOUNTING_EDIT))){
                    ProNav_Auth::unauthorized();
                }
                break;
            case 'edit-acquisition':
            case 'commit-acquisition':
                if(!(ProNav_Auth::hasAccessToProjectID($project_id) && ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SALES_EDIT))){
                    ProNav_Auth::unauthorized();
                }
                break;
            case 'commit-schedule':
            case 'edit-schedule':
            case 'edit-project-info':
            case 'update-project-info':
            case 'promote-to-project':
                if (!(ProNav_Auth::hasAccessToProjectID($project_id) || !ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_OVERVIEW_EDIT))){
                    ProNav_Auth::unauthorized();
                }            
                break;
            case 'get-users-for-dept':
                if(!ProNav_Auth::isEmployee() && !ProNav_Auth::isInDepartment($workgroup_id)){
                    ProNav_Auth::unauthorized(); 
                }
                break;
            case 'get-users-for-corp':
                if(!ProNav_Auth::isEmployee() && ($corporation_id != ProNav_Auth::getCorporationID())){
                    ProNav_Auth::unauthorized(); 
                }
                break;  
            case 'view-submittal':
                if (!ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SUBMITTAL_VIEW) || !ProNav_Auth::hasAccessToProjectID($project_id)){
                    ProNav_Auth::unauthorized();
                }
                break;                               
            case 'edit-submission':
            case 'commit-submission':
            case 'edit-submittal':
            case 'commit-submittal':
            case 'release-order':                
            case 'commit-release-order':
                if (!ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SUBMITTAL_ADD_EDIT, ProNav_Auth::PERM_PROJECTS_SUBMITTAL_UPDATE) || !ProNav_Auth::hasAccessToProjectID($project_id))
                {
                    ProNav_Auth::unauthorized();  
                }
                break; 
            case 'other-projects':
                if(!ProNav_Auth::isEmployee()){
                    ProNav_Auth::unauthorized();
                }
                break;             
            case 'edit-journal':
            case 'view-hindrance':
            case 'journal-letter':
                if(!ProNav_Auth::hasPerm(
                ProNav_Auth::PERM_PROJECTS_JOURNAL_VIEW,
                ProNav_Auth::PERM_PROJECTS_JOURNAL_ADD,
                ProNav_Auth::PERM_PROJECTS_JOURNAL_ACKNOWLEDGE_AND_HINDRANCE))
                {
                    ProNav_Auth::unauthorized();
                }
                break;
            case 'save-project-billing-to-corp':
                if(!ProNav_Auth::isEmployee() || !ProNav_Auth::hasPerm(ProNav_Auth::PERM_CORPORATIONS_ADD_EDIT)){
                    ProNav_Auth::unauthorized($this);
                }
                break;
        }

        //If you don't belong to any departments or your corporation doesn't have any locations you can't create a project.
        //You shouldn't even have the tab - so you entered the url manually.
        switch ($this->getRequest()->getActionName())
        {
            case 'create':
            case 'save-new':
                if (count(ProNav_Auth::getDepartments()) == 0 || 
                count(Application_Model_Locations::getCorporationLocations(ProNav_Auth::getCorporationID()))==0)
                {
                    ProNav_Auth::unauthorized();
                }
                break;
        } 

        if($this->_request->isXmlHttpRequest()){
            $this->_helper->viewRenderer->setNoRender();
            $this->_helper->layout->disableLayout(); 
        }        
    }    

    /** General Project **/

    public function indexAction()
    {  
        //$filterSession = new Zend_Session_Namespace('ProNav_Filter');            
        //$filter = Zend_Json::decode($filterSession->filter); 

        $this->_helper->viewRenderer->setNoRender();
        $this->view->isEmployee = ProNav_Auth::IsEmployee();
        $filter = array();

        $row = Application_Model_Projects::GetDefaultFilter();
        if($row)
        {
            $filter = Zend_Json::decode($row->project_filter);
            $this->view->filter = $filter;
        }         

        if($this->view->isEmployee){

            $this->view->trimgroups = Application_Model_Corporations::GetWorkgroups(ProNav_Utils::TriMId,true);
            $this->view->corporations = Application_Model_Corporations::GetAllCorporations(true);            

            if($filter["done_for_corporation"])
            {
                $this->view->departments = Application_Model_Corporations::GetWorkgroups($filter["done_for_corporation"],true);
            }

            if($filter["location_owner"])
            {
                $this->view->locations = Application_Model_Locations::getCorporationLocations($filter["location_owner"],true);  
            }

            $this->renderScript('project/index.phtml');
        } 
        else
        {
            $this->view->corpUsers = Application_Model_Users::getCompanyUsers(ProNav_Auth::getCorporationID());
            $this->view->departments = Application_Model_Users::GetUserWorkgroups(ProNav_Auth::getUserID());
            $this->view->locations = Application_Model_Locations::getCorporationLocations(ProNav_Auth::getCorporationID());
            $this->view->busUnits = Application_Model_Workgroups::getCorporationWorkgroups(ProNav_Utils::TriMId);
            $this->renderScript('project/index-c.phtml');
        }

    }

    public function viewAction()
    {                
        $project_id = $this->_getParam('id');          
        $this->view->selected_tab = $this->_getParam("tab");

        //PROJECT DATA
        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);

			
        if(!$projectInfo->project_id) {
            ProNav_Auth::unauthorized();
        }

        $this->view->open_projects = Application_Model_Projects::OpenProjectsCountAtLocation($projectInfo->done_at_location, $project_id);
        $this->view->active_projects = Application_Model_Projects::ActiveProjectsCountAtLocation($projectInfo->done_at_location, $project_id);
        $this->view->project_id = $projectInfo->project_id;
        $this->view->projectinfo = $projectInfo;

        $executed_cors = array();
        $cors = Application_Model_ChangeOrders::getRequestsForProject($project_id, true, true);
        foreach ($cors as $cor){
            if ($cor->accounting_stage_id == Application_Model_ChangeOrderRequest::ACCT_EXECUTED){
                $executed_cors[] = $cor;
            }
        }        

        //Overview
        $this->view->general_info = $this->view->partial('project/partial-general-info.phtml', array(
            'projectinfo'=>$projectInfo,
            'open_projects' => $this->view->open_projects,
            'active_projects' => $this->view->active_projects
            )
        );       

        //Project Value
        if (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_VALUE_VIEW)){
            $this->view->project_value = $this->view->partial('project/partial-project-value.phtml', 
                array(
                    'base_project' => $projectInfo, 
                    'original_contract_value' => $projectInfo->getProjectValue(),
                    'has_executed_cos' => count($executed_cors),
                    'executed_cos_value' => Application_Model_ChangeOrders::sumProjectValue($executed_cors),
                    'total_contract_value' => Application_Model_ChangeOrders::sumProjectValue($executed_cors, $projectInfo)
                )
            );
        }

		
		
		//Material Location
		$this->view->material = $this->view->partial('project/material-location.phtml',array('projectinfo' => $projectInfo));
   
        //Scope of Work
        if (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SCOPE_VIEW)){
            $this->view->scope = $this->view->partial('project/scope.phtml',array('projectinfo' => $projectInfo));
        }

        //Accounting
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_ACCOUNTING_VIEW)){
            $this->view->accounting = $this->view->partial('project/accounting.phtml', array('projectinfo'=> $projectInfo));
        }

        //Work Acquisition/Sales
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SALES_VIEW)){
            $this->view->work_acquisition = $this->view->partial('project/acquisition.phtml',array('project' => $projectInfo));
        }

        //Tags
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_TAGS_GENERAL_ACCESS)){
            $this->view->tagSelections = Application_Model_Tag::getTagsFor(Application_Model_Tag::OBJ_PROJECT, $project_id);
        }

        //Stage History
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_HISTORY_VIEW)){
            $this->view->stagehistory = $this->view->partial('project/partial-stage-history.phtml', 
                array('stagehistory' => Application_Model_Projects::getStageHistory($project_id)));
        }

        //Change Order Requests
        if (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_CHANGE_ORDER_VIEW)){
            $this->view->cors = $this->view->partial('change-order/partial-request-table.phtml',
                array('projectInfo' => $projectInfo, 'cors' => $cors));
        } 

        //Project Notes
        if (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_NOTES_VIEW)){                                                                      
            $this->view->project_notes = $this->getNotesModule($projectInfo->project_id);
        }

        //Customer Notes
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_CUSTOMER_NOTES)){            
            $this->view->customerNotes = Application_Model_Notes::getProjectRelevantNotes($projectInfo);
            $this->view->customerNotesTable = $this->view->partial('note/note-entity-project-stack.phtml',
                array('customerNotes' => $this->view->customerNotes, 'projectInfo' => $projectInfo));
        }

        //FILES
        if (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_FILES_VIEW_TAB)){
            $rootFolder = Application_Model_FileFolders::getFolder(Application_Model_File::OBJ_PROJECT, $project_id, null, true);
            $this->view->files = $this->view->partial('file/partial-file-module.phtml',
                array(
                    'folder' => $rootFolder,
                    'obj_type' => Application_Model_File::OBJ_PROJECT,
                    'obj_id' => $project_id
                )
            );
        } 

        if (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_PROGRESS_VIEW_TAB)){

            //Progress
            //Items only relevant once the project has reached an open status
            /*
            if ($projectInfo->isSecured()){
            $this->view->status = $this->view->partial('project/status.phtml',array('projectinfo'=>$projectInfo));
            }
            */

            //Project Tasks
            $this->view->project_tasks = $this->view->partial('project-tasks/view.phtml', array(
                'task_groups' => Application_Model_ProjectTask::get_tasks_by_project_id($project_id),
                'projectinfo' => $projectInfo,
                'progress_options' => Application_Model_ProgressOptions::get(true, true)
                )
            );
            
            //Punch Lists
            /*
            $this->view->project_punch_list = $this->view->partial('project-punch-list/view.phtml', array(
                'punch_list' => Application_Model_ProjectPunchList::get_items_by_project_id($project_id)
            
            ));
            */

            //Project Labor 
            if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_LABOR_PROD_VIEW)){    
                $this->view->project_labor = $this->view->partial('project-labor/view.phtml', array('labor' => Application_Model_ProjectLabor::getLaborSummary($project_id)));            
            }

            //Post Project Analysis (PPA)
            if ($projectInfo->isStatus100Percent() && ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_PPA_VIEW)){
                $this->view->project_analysis = $this->view->partial('project-analysis/view.phtml', array(
                    'project_id' => $project_id, 
                    'analysis' => Application_Model_ProjectAnalysisType::get_work_types_for_project($project_id)
                    )
                );                        
            }   
        }


        //RFIS
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_RFI_VIEW)){
            $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
            $this->view->rfis = $this->view->partial('project/partial-rfis.phtml', array('rfis' => $rfis));      
        }  

        //Submittals
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_SUBMITTAL_VIEW)){
            $submittals = Application_Model_Submittals::getSubmittalsForProject($project_id);
            $this->view->submittals = $this->view->partial('project/partial-submittals.phtml', array('submittals' => $submittals));
        }          

        //Job Journals
        //since there can be hundreds of journals, don't get a deeply-populated array like rfis and submittals
        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_JOURNAL_VIEW)){
            $journal_log = Application_Model_Journals::getJournalsLog($project_id);
            $this->view->journals = $this->view->partial('project/partial-journals.phtml', array('journals' => $journal_log, 'project_id' => $project_id));
            $this->view->has_incomplete_journal = Application_Model_Journals::userHasIncomplete($journal_log);
        } 

        //Project Team      
        If (!ProNav_Auth::isEmployee() || ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_TEAM_VIEW)){
            $projectTeam = Application_Model_Projects::getProjectTeam($project_id);
            $this->view->project_team = $this->view->partial('project/project-team.phtml',array('users' => $projectTeam));
            $this->view->userIsOnTeam = array_key_exists(ProNav_Auth::getUserID(), $projectTeam);
        }

        //Schedule
        //$this->view->schedule = $this->view->partial('project/schedule.phtml',array('projectinfo'=> $projectInfo));
    }

    public function globalSearchAction()
    {
        $keyword = $this->_getParam('global_search'); 
        echo Zend_Json::encode(Application_Model_Projects::QuickSearch($keyword));
    }        

    public function createAction()
    { 

        $user_id = ProNav_Auth::getUserID();  
        $corporation_id = ProNav_Auth::getCorporationID(); 
        $this->_helper->viewRenderer->setNoRender();      

        $prevbunit = Application_Model_Users::GetPrevBusinessUnit($user_id);

        $isEmployee = ProNav_Auth::IsEmployee();   

        if($isEmployee){
            $wkgp_users = Application_Model_Users::getWorkgroupUsers($prevbunit);
            echo $this->view->partial('project/partial-create-t.phtml', 
                array(
                    'type' => 'public',
                    'trimgroups' => Application_Model_Corporations::GetWorkgroups(ProNav_Utils::TriMId),
                    'corporations' => Application_Model_Corporations::GetAllCorporations(),
                    'stages' => Application_Model_Projects::GetStages($isEmployee),
                    'prevbunit' => $prevbunit,
                    'trim_emps' => Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId),
                    'project_managers' => Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acct_project_manager, true),
                    'field_staff_leaders' => Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acct_field_staff_leader, true),
                    'booking_probability' => Application_Model_Projects::getProjectPercentages(),        
                    'estimating_complete_options' => Application_Model_List::getList(
                        array('table' => 'project_percentages_estimating',
                            'idKey' => 'project_estimating_id')
                    ),
                    'proposal_types' => Application_Model_List::getList(array(
                        'table' => 'project_bill_types', 
                        'idKey' => 'bill_type',
                        'sortKey' => 'display_order',
                        'displayKey' => 'title'
                    ))                                               
                )
            );
        }else{
            echo $this->view->partial('project/partial-create-c.phtml', 
                array(
                    'trimgroups' => Application_Model_Corporations::GetWorkgroups(ProNav_Utils::TriMId),
                    'departments' => Application_Model_Users::GetUserWorkgroups($user_id),
                    'locations' => Application_Model_Locations::getCorporationLocations($corporation_id),
                    'prevbunit' => $prevbunit,
                    'require_po' => Application_Model_Corporations::isPORequired(ProNav_Auth::getCorporationID()),
                    'users' => $this->getUsersForCorp($corporation_id)
                )
            );
        }

    }  

    public function saveNewAction()
    {
        $jOut = array();

        $data = ProNav_Utils::stripTagsAndTrim(Zend_Json::decode($this->_getParam('data')));

        if(!ProNav_Auth::IsEmployee())
        {
            $data['done_for_corporation'] = ProNav_Auth::getCorporationID();
            $data['location_owner'] = $data['done_for_corporation'];
            $data['location_owner'] =  ProNav_Auth::getCorporationID();
            $data['bill_type'] = $data['stage_id'] == ProNav_Utils::STAGE_AUTHORIZED ? Application_Model_Project::BILL_TYPE_TM :  new Zend_Db_Expr('Null');
        }

        $errors = $this->validateNew($data);

        $new_project_id = 0;
        if(count($errors) == 0){
            $new_project_id = Application_Model_Projects::CreateProject($data);
        }

        $jOut['errors'] = $errors;
        $jOut['project_id'] = $new_project_id;

        echo Zend_Json::encode($jOut);                                         
    }   

    public function editProjectInfoAction()
    {
        $this->_helper->viewRenderer->setNoRender(false); 
        $project_id = $this->_getParam('id');
        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);
        $this->view->projectinfo = $projectInfo;
        $this->view->stages = Application_Model_Projects::GetStages(ProNav_Auth::isEmployee());
        $this->view->billtypes = Application_Model_Projects::GetBillTypes(true);
        $this->view->custworkgroups =  Application_Model_Corporations::GetWorkgroups($projectInfo->done_for_corporation,true);
        $this->view->trimworkgroups =  Application_Model_Corporations::GetWorkgroups(ProNav_Utils::TriMId,true); 
        $this->view->corporations = Application_Model_Corporations::GetAllCorporations(true);
        $this->view->locations = Application_Model_Locations::getCorporationLocations($projectInfo->DoneAtLocation->corporation_id,true); 
        //$this->view->trimusers = Application_Model_Users::GetUsersForWorkgroup($projectInfo->done_by_workgroup,true); 
        $this->view->custusers = Application_Model_Corporations::GetUsers($projectInfo->done_for_corporation,true); 
    }

    public function updateProjectInfoAction()
    {
        $project_id = $this->_getParam('id');
        $project = Application_Model_Projects::GetProjectInfo($project_id);

        $data = Zend_Json::decode($this->_getParam('data'));
        $data = ProNav_Utils::stripTagsAndTrim($data);

        $data['done_for_corporation'] = (is_numeric($data['done_for_corporation']) ? $data['done_for_corporation'] : -1);
        $data['done_for_workgroup'] = (is_numeric($data['done_for_workgroup']) ? $data['done_for_workgroup'] : 0);
        $data['done_at_location'] = (is_numeric($data['done_at_location']) ? $data['done_at_location'] : 0);
        $data['done_by_workgroup'] = (is_numeric($data['done_by_workgroup']) ? $data['done_by_workgroup'] : -1);
        $data['stage_id'] = (is_numeric($data['stage_id']) ? $data['stage_id'] : -1);
        $data['point_of_contact'] = (is_numeric($data['point_of_contact']) ? $data['point_of_contact'] : new Zend_Db_Expr("NULL"));
        $data['requested_by'] = ($data['requested_by'] ? $data['requested_by'] : new Zend_Db_Expr("NULL"));
        $data['ref_no'] = ($data['ref_no'] ? trim($data['ref_no']) : new Zend_Db_Expr("NULL"));
        $data['schedule_not_before'] = ProNav_Utils::toMySQLDate($data['schedule_not_before']);
        $data['schedule_required_by'] = ProNav_Utils::toMySQLDate($data['schedule_required_by']);

        $errors = array();

        //Required fields - arrays are 0=field to test, 1=value that is unacceptable, 2=User friendly description.
        $req_fields = array(
            array('stage_id', -1, 'Stage'),
            array('title', '', 'Title')
        );

        if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_OVERVIEW_EDIT))
        {
            $req_fields = array_merge($req_fields, 
                array(
                    array('done_for_corporation', -1, 'Corporation'),
                    array('done_for_workgroup', 0, 'Workgroup'),
                    array('done_at_location', 0, 'Location'),
                    array('done_by_workgroup', -1, 'Business Unit')
                )
            );
        } else {
            //These cant be set for change orders or for people who don't have permission.                
            unset($data['done_for_corporation']);
            unset($data['done_for_workgroup']);
            unset($data['done_at_location']);
            unset($data['done_by_workgroup']);
        }

        foreach ($req_fields as $rule) {
            if ($data[$rule[0]] == $rule[1]) {
                $errors[] = sprintf("A %s is required.",$rule[2]);
            }
        }

        $closeChecks = Application_Model_Projects::getCloseDetails($project_id);

        if($data['stage_id'] == ProNav_Utils::STAGE_CLOSED && $closeChecks->current_stage != ProNav_Utils::STAGE_CLOSED)            {
            if($closeChecks->current_status_percent != Application_Model_ProgressOption::STATUS_TASK_COMPLETE){
                $errors[] = "The project's overall status must be set to 100% before it can be closed.";                 
            }
        }

        if (($data['stage_id'] == ProNav_Utils::STAGE_CLOSED || $data['stage_id'] == ProNav_Utils::STAGE_CANCELLED)){
            if($closeChecks->invalid_cor_state)
                $errors[] = "Project cannot be closed with outstanding Change Order Requests.";   
        }

        $toCancel = $data['stage_id'] == ProNav_Utils::STAGE_CANCELLED && $closeChecks->current_stage != ProNav_Utils::STAGE_CANCELLED;
        $toHold = $data['stage_id'] == ProNav_Utils::STAGE_HOLD && $closeChecks->current_stage != ProNav_Utils::STAGE_HOLD;
        if($data['stage_comment'] == "" && ($toCancel || $toHold)){
            $errors[] = "Comment required for this stage change.";
        }

        if (count($errors) == 0){
            Application_Model_Projects::UpdateProjectInfo($project_id, $data);
        }

        echo Zend_Json::encode(array('errors'=>$errors));
    }

    public function updateStageAction(){

        $project_id = $this->_getParam('id');
        $data = Zend_Json::decode($this->_getParam('data'));
        $to_stage_override = $data['edit_hold_stage_override'];
        $to_stage = $this->_getParam('to_stage');
        $from_stage = $this->_getParam('from_stage');
        $result = array(
            'status' => 0
        );

        $projectinfo_orig = Application_Model_Projects::GetProjectInfo($project_id);

        //for the 'release from hold' operation, the user can override the to_stage with their own selection.
        if (is_numeric($to_stage_override)){
            $to_stage = $to_stage_override;
        }

        $data['to_stage'] = $to_stage;

        $data = ProNav_Utils::stripTagsAndTrim($data);

        if (!is_numeric($to_stage) || !is_numeric($from_stage)){
            echo Zend_Json::encode(array('errors'=>array('Unrecognized Action. Please Try Again.')));
            return;
        }

        if ($to_stage == $from_stage && $to_stage != ProNav_Utils::STAGE_PROPOSED){
            echo Zend_Json::encode(array('errors'=>array('Cannot update a project into the same stage.')));
            return;
        }

        if ($data['comment'] == '' && 
        ($data['to_stage'] == ProNav_Utils::STAGE_HOLD || $data['to_stage'] == ProNav_Utils::STAGE_CANCELLED)){
            echo Zend_Json::encode(array('errors'=> array('A Comment is Required.')));
            return;
        }

        if(!ProNav_Auth::isEmployee() && $to_stage == ProNav_Utils::STAGE_AUTHORIZED && !$data['authorization_type']){
            echo Zend_Json::encode(array('errors'=>array('An Authorization Selection Is Required')));
            return;
        }                    

        if ($to_stage == ProNav_Utils::STAGE_PROPOSED){
            if (!is_numeric($data['bill_type']) || is_numeric($data['bill_type']) && $data['bill_type'] < 1)
            {
                echo Zend_Json::encode(array('errors'=>array('A Propsal Type Selection is Required.')));
                return;
            }            
        }

        if ($to_stage == ProNav_Utils::STAGE_CLOSED || $to_stage == ProNav_Utils::STAGE_CANCELLED){
            $closeChecks = Application_Model_Projects::getCloseDetails($project_id);    

            $errors = array();

            if($to_stage == ProNav_Utils::STAGE_CLOSED){
                if($closeChecks->current_status_percent != Application_Model_ProgressOption::STATUS_TASK_COMPLETE)
                    $errors[] = "The project's overall status must be set to 100% before it can be closed.";             
            }

            if($closeChecks->invalid_cor_state){
                $errors[] = "Project cannot be closed with outstanding Change Order Requests.";                          
            }

            /* Doesn't exist in close checks?
            if($closeChecks->unapproved_co) {
            $errors[] = "All Change Orders must be executed first.";        
            }
            */

            if (count($errors) > 0){
                echo Zend_Json::encode(array('errors' => $errors));
                return;                            
            }            
        }

        //set the project manager to null if it exists and it is not a number. 
        if ($to_stage == ProNav_Utils::STAGE_OPEN && array_key_exists('acct_project_manager', $data) && !is_numeric($data['acct_project_manager'])){
            $data['acct_project_manager'] = null;
        }

        if (isset($data['done_for_corporation']) && $data['done_for_corporation'] != $projectinfo_orig->done_for_corporation){
            //Get limited billing information from the corporation (most was set in dialog). 
            $billing = Application_Model_Corporations::getBillingInfo($data['done_for_corporation']);
            $data['acct_billing_contact'] = $billing['billing_contact'];
            $data['acct_billing_phone'] = $billing['billing_phone'];            
        }       

        Application_Model_Projects::UpdateStage($project_id, $data);
        $projectinfo = Application_Model_Projects::GetProjectInfo($project_id);

        if (isset($data['acct_billing_address1']) && ProNav_Auth::hasPerm(ProNav_Auth::PERM_CORPORATIONS_ADD_EDIT)){
            //Determine if you should prompt the user to update the corp billing info. 
            $fields_to_update = $this->getEligibleBillingFieldsForCorpUpdate($data, $projectinfo_orig, $projectinfo);
            if (count($fields_to_update)>0){
                $result['offer_save_billing'] = $fields_to_update;   
            }
        }

        $result['status'] = 1;
        $this->getResponse()->setBody(Zend_Json::encode($result));
    }

    /** End General Project **/

    /** Journals **/

    public function editJournalAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam('id', -1);
        $journal_id = $this->_getParam('journal_id', 0);

        $journal = Application_Model_Journals::GetJournalInfo($journal_id, $project_id);

        $this->view->journal_id = $journal_id;
        $this->view->project_id = $project_id;
        $this->view->journal = $journal;
        $this->view->temperatures = Application_Model_Journals::JournalTemperaturesList();
        $this->view->weathers = Application_Model_Journals::getJournalWeathers($journal->weather_bitwise);
        $this->view->personnel_roles = Application_Model_Journals::getJournalRoles($journal_id, $project_id);
        $this->view->contractors = Application_Model_Journals::getJournalContractors($journal_id, $project_id);
        $this->view->equipment = Application_Model_Journals::getJournalEquipment($journal_id, $project_id);

        if(!is_null($journal->modified_by)){
            $this->view->updated_by = $journal->ModifiedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->modified_date);
        }else if(!is_null($journal->created_by)){
            $this->view->updated_by = $journal->CreatedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->created_date);
        }

    }

    public function viewJournalAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam('id', -1);
        $journal_id = $this->_getParam('journal_id', 0);

        $journal = Application_Model_Journals::GetJournalInfo($journal_id, $project_id);

        $this->view->journal_id = $journal_id;
        $this->view->project_id = $project_id;
        $this->view->journal = $journal;
        $this->view->weathers = Application_Model_Journals::getJournalWeathers($journal->weather_bitwise);
        $this->view->personnel_roles = Application_Model_Journals::getJournalRoles($journal_id);
        $this->view->contractors = Application_Model_Journals::getJournalContractors($journal_id);
        $this->view->equipment = Application_Model_Journals::getJournalEquipment($journal_id);
        $this->view->supp_comments = Application_Model_Journals::getJournalSupplementalComments($journal_id);

        $this->view->breadcrumb = Application_Model_Journals::journalNumBreadCrumb($journal_id, $project_id);

        if(!is_null($journal->modified_by)){
            $this->view->updated_by = $journal->ModifiedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->modified_date);
        }else if(!is_null($journal->created_by)){
            $this->view->updated_by = $journal->CreatedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->created_date);
        } 
    }

    public function journalLetterAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam("id", -1);
        $journal_id = $this->_getParam("journal_id", 0);

        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);
        $this->view->project_title = $projectInfo->title;
        $this->view->project_job_no = $projectInfo->job_no;

        $journal = Application_Model_Journals::GetJournalInfo($journal_id);
        $this->view->journal = $journal;
        $this->view->weathers = Application_Model_Journals::getJournalWeathers($journal->weather_bitwise);
        $this->view->personnel_roles = Application_Model_Journals::getJournalRoles($journal_id);
        $this->view->contractors = Application_Model_Journals::getJournalContractors($journal_id);
        $this->view->equipment = Application_Model_Journals::getJournalEquipment($journal_id);
        $this->view->supp_comments = Application_Model_Journals::getJournalSupplementalComments($journal_id);

        if(!is_null($journal->modified_by)){
            $this->view->updated_by = $journal->ModifiedBy->getFormattedName();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->modified_date);
        }else if(!is_null($journal->created_by)){
            $this->view->updated_by = $journal->CreatedBy->getFormattedName();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->created_date);
        }

    }

    public function listJournalRoleAction()
    {
        $project_id = $this->_getParam('id', -1);
        $journal_id = $this->_getParam('journal_id', 0);
        $this->_helper->viewRenderer->setNoRender();

        echo Zend_Json::encode(Application_Model_Journals::JournalRolesList());

    }

    public function commitAddEditJournalAction()
    {            
        $project_id = $this->_getParam('id', -1);
        $journal_id = $this->_getParam('journal_id', 0);            
        $data = ProNav_Utils::stripTagsAndTrim(Zend_Json::decode($this->_getParam('data')));

        $this->_helper->viewRenderer->setNoRender();

        $data['project_id'] = $project_id;
        $data['journal_date'] = ProNav_Utils::toMySQLDate($data['journal_date']);
        $data["modified_by"] = ProNav_Auth::getUserID();
        $data["modified_date"] = new Zend_Db_Expr("NOW()"); 
        if($journal_id == 0){
            $data["created_by"] = ProNav_Auth::getUserID();
            $data["created_date"] = new Zend_Db_Expr("NOW()");
        }

        $errors = array();

        //check if journal date is valid
        $errors = array_merge($errors, Application_Model_Journals::isJournalDateValid($journal_id, $project_id, $data['journal_date']));


        if(!count($errors)){
            $journal_id = Application_Model_Journals::addEditJournal($journal_id, $data);   
        }                 

        echo Zend_Json::encode(
            array(
                'journal_id'=> $journal_id, 
                'status' => (count($errors)==0 ? 1 : 0),
                'errors' => $errors)
        );
    }

    public function journalFilesAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $obj_id = $this->_getParam('obj_id',-1);
        $obj_type = Application_Model_File::OBJ_JOURNAL;
        $journal = Application_Model_Journals::GetJournalInfo($obj_id);
        $files = Application_Model_Files::getFiles($obj_type, $obj_id,null,false,1);
        $this->getResponse()->setBody($this->view->partial('/project/partial-journals-files.phtml',
            array(
                'files'=>$files, 
                'obj_type' => $obj_type, 
                'obj_id'=> $obj_id, 
                'submitted' => $journal->submitted,
                'submitted_date' => $journal->getSubmittedDateTime())
            )
        );
    }

    public function journalSuppCommentAction(){
        $journal_id = $this->_getParam('journal_id');
        $journal = Application_Model_Journals::GetJournalInfo($journal_id);

        if ($journal->created_by != ProNav_Auth::getUserID()){
            $this->getResponse()->setBody(Zend_Json::encode(array('status' => 0, 'error'=>'Only the journal submitter may add additional comments.')));

        } else {

            $comment = ProNav_Utils::stripTagsAndTrim($this->_getParam('comment',''));

            if (strlen(comment) == 0){
                echo Zend_Json::encode(array('status'=>0, 'error'=>'Please enter a comment'));                
            } else {
                $new_comment = Application_Model_Journals::addJournalSupplementalComment($journal_id, $comment);
                echo Zend_Json::encode(array('status'=>1, 'comment'=>$new_comment));
            }   
        }
    }

    /** End Journals **/

    /** Hindrances **/

    public function viewHindranceAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam('id', -1);
        $journal_id = $this->_getParam('journal_id', 0);
        $edit_mode = $this->_getParam('edit_mode',0); 

        $journal = Application_Model_Journals::GetJournalInfo($journal_id, $project_id);
        if(!is_null($journal->hindrance_ack_by)){
            $this->view->updated_by = $journal->AcknowledgedBy->getFormattedName();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($journal->hindrance_ack_date);
        }

        $this->view->journal = $journal;
        $this->view->edit_mode = $edit_mode;

    }

    public function commitHindranceAction()
    {
        $project_id = $this->_getParam('id', -1);
        $journal_id = $this->_getParam('journal_id', 0);            
        $data = ProNav_Utils::stripTagsAndTrim(Zend_Json::decode($this->_getParam('data')));

        $this->_helper->viewRenderer->setNoRender();

        $data["hindrance_ack_by"] = ProNav_Auth::getUserID();
        $data["hindrance_ack_date"] = new Zend_Db_Expr("NOW()");

        $n = Application_Model_Journals::updateHindrance($journal_id, $data);
        if ($n == 0){
            $this->getResponse()->setBody(Zend_Json::encode(array('status'=>0, 'errors'=>'Journal update operation failed.')));
        } else {
            $journal_log = Application_Model_Journals::getJournalsLog($project_id);
            $journals = $this->view->partial('project/partial-journals.phtml', 
                array('journals' => $journal_log, 'project_id' => $project_id)
            );

            $this->getResponse()->setBody(Zend_Json::encode(array('status'=>1,'section'=>$journals)));                
        }                                                        
    }

    /** End Hindrances **/

    /** Accounting **/

    public function editAccountingAction()
    {
        $data = Zend_Json_Decoder::decode($this->_getParam('data'));
        $project_id = $data["project_id"];

        $project = Application_Model_Projects::GetProjectRow($project_id);

        if (!$project->project_id){
            ProNav_Auth::pageNotFound();
        }

        $this->_helper->viewRenderer->setNoRender(false);
        $this->view->projectinfo = $project;
        $this->view->billTypes = Application_Model_Projects::GetBillTypes(true,true);
        $this->view->InvoiceTypes = Application_Model_Projects::getInvoiceTypes();
        $this->view->stages = Application_Model_Projects::GetStages(ProNav_Auth::isEmployee());
        $this->view->project_managers = Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acct_project_manager, true);
        $this->view->project_engineers = Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acct_project_engineer, true);
        $this->view->field_staff_leaders = Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acct_field_staff_leader, true);        
        $this->view->states = Application_Model_USStates::getStateList(true);
        $this->view->project_types = Application_Model_List::getList('project_types', $project->acct_project_type);
    }

    public function commitAccountingAction()
    {        
        $data = Zend_Json::decode($this->_getParam('data'));
        $project_id =  Zend_Filter_Digits::filter($data["project_id"]);

        //Used to determine if billing items changed, prompted later below. 
        $projectinfo_orig = Application_Model_Projects::GetProjectInfo($project_id);

        $data["project_id"] = $project_id;

        ProNav_Utils::WhiteListArray($data,array(
            'project_id',
            'bill_type',
            'po_amount',
            'po_date',
            'schedule_estimated_start',
            'schedule_estimated_end',
            'acct_project_value',
            'acct_invoice_type', 
            'acct_ocip',
            'id', 
            'acct_retainage',
            'acct_prevailing_wage',
            'acct_tax_exempt',
            'acct_cert_of_ins_req',
            'acct_performance_bond_req',
            'acct_permit_req',
            'acct_project_type',
            'acct_billed_to_date',
            'acct_estimated_cost',
            'acct_comment',
            'po_number',
            'job_no', 
            'acct_project_manager',
            'acct_project_engineer', 
            'acct_field_staff_leader', 
            'acct_billing_date',
            'acct_billing_address1',
            'acct_billing_address2',
            'acct_billing_city',
            'acct_billing_state',
            'acct_billing_zip',
            'acct_billing_contact',
            'acct_billing_phone',
            'acct_billing_notes',
        ));

        $data = ProNav_Utils::stripTagsAndTrim($data);

        $data["bill_type"] = is_numeric($data["bill_type"]) ? $data["bill_type"] : -1;
        $data["po_amount"] = ProNav_Utils::stripFormattedNumbers($data["po_amount"]);
        $data["po_date"] = ProNav_Utils::toMySQLDate($data["po_date"]);
        $data["acct_project_value"] = ProNav_Utils::stripFormattedNumbers($data["acct_project_value"]);
        $data["acct_invoice_type"] = is_numeric($data["acct_invoice_type"]) ? $data["acct_invoice_type"] : -1;
        $data["acct_prevailing_wage"] = $data["acct_prevailing_wage"] == 1 ? 1 : 0;
        $data["acct_tax_exempt"] = $data["acct_tax_exempt"] == 1 ? 1 : 0;
        $data["acct_ocip"] = $data["acct_ocip"] == 1 ? 1 : 0;
        $data["acct_cert_of_ins_req"] = $data["acct_cert_of_ins_req"] == 1 ? 1 : 0;
        $data["acct_performance_bond_req"] = $data["acct_performance_bond_req"] == 1 ? 1 : 0;
        $data["acct_permit_req"] = $data["acct_permit_req"] == 1 ? 1 : 0;
        $data["acct_project_type"] = (is_numeric($data["acct_project_type"]) ? $data["acct_project_type"] : 1);
        $data["acct_billed_to_date"] = ProNav_Utils::stripFormattedNumbers($data["acct_billed_to_date"]);
        $data["acct_estimated_cost"] = ProNav_Utils::stripFormattedNumbers($data["acct_estimated_cost"]);
        $data["acct_project_manager"] = (is_numeric($data["acct_project_manager"]) ? $data["acct_project_manager"] : null);
        $data["acct_project_engineer"] = (is_numeric($data["acct_project_engineer"]) ? $data["acct_project_engineer"] : null);
        $data["acct_field_staff_leader"] = (is_numeric($data["acct_field_staff_leader"]) ? $data["acct_field_staff_leader"] : null);
        $data['acct_billing_date'] = $data['acct_billing_date'] && is_numeric($data['acct_billing_date']) ? $data['acct_billing_date'] : null;
        $data["acct_billing_state"] = (is_numeric($data["acct_billing_state"]) ? $data["acct_billing_state"] : null);
        $data['schedule_estimated_start'] = ProNav_Utils::toMySQLDate($data['schedule_estimated_start'],false);
        $data['schedule_estimated_end'] = ProNav_Utils::toMySQLDate($data['schedule_estimated_end'],false);

        ProNav_Utils::scrubArray($data, true, false);

        $data["acct_modified_by"] = ProNav_Auth::getUserID();
        $data["acct_modified_date"] = new Zend_Db_Expr("NOW()");

        $result = Application_Model_Projects::updateProject($data);

        //The updated project data. 
        $projectinfo = Application_Model_Projects::GetProjectInfo($project_id);      

        $cors = Application_Model_ChangeOrders::getRequestsForProject($project_id, true, true);
        $executed_cors = array();
        foreach ($cors as $cor){
            if ($cor->accounting_stage_id == Application_Model_ChangeOrderRequest::ACCT_EXECUTED)
                $executed_cors[] = $cor;
        }

        $jOut = array();
        if (!$result) {
            $jOut['status'] = 0;
        } else {

            $jOut['status'] = 1;
            $jOut['accounting'] = $this->view->partial('project/accounting.phtml', array('projectinfo' => $projectinfo));                
            $jOut['general'] = $this->view->partial('project/partial-general-info.phtml', array(
                'projectinfo' => $projectinfo,
                'open_projects' => Application_Model_Projects::OpenProjectsCountAtLocation($projectinfo->done_at_location, $project_id),
                'active_projects' => Application_Model_Projects::ActiveProjectsCountAtLocation($projectinfo->done_at_location, $project_id)
            ));

            $jOut['value'] = $this->view->partial('project/partial-project-value.phtml', 
                array(
                    'base_project' => $projectinfo, 
                    'original_contract_value' => $projectinfo->getProjectValue(),
                    'has_executed_cos' => count($executed_cors),
                    'executed_cos_value' => Application_Model_ChangeOrders::sumProjectValue($executed_cors),
                    'total_contract_value' => Application_Model_ChangeOrders::sumProjectValue($executed_cors, $projectinfo)
                )
            );   

            //Determine if you should prompt the user to update the corp billing info. 
            if (ProNav_Auth::hasPerm(ProNav_Auth::PERM_CORPORATIONS_ADD_EDIT)){                                    
                $fields_to_update = $this->getEligibleBillingFieldsForCorpUpdate($data, $projectinfo_orig, $projectinfo);
                if (count($fields_to_update)>0){
                    $jOut['offer_save_billing'] = Zend_Json::encode($fields_to_update);   
                } 
            }
        } 

        echo Zend_Json_Encoder::encode($jOut);
    }

    public function saveProjectBillingToCorpAction(){

        //You will get a list of fields for which you should update the billing
        //info on the done_for_corporation with the values from the project table. 
        $project_id = $this->_getParam('project_id');
        $flds = $this->_getParam('flds');

        $projectinfo = Application_Model_Projects::GetProjectInfo($project_id);
        if ($projectinfo){

            Application_Model_Corporations::updateBillingInfoFromProject($projectinfo);

            $update = array(
                'corporation_id' => $projectinfo->done_for_corporation,
                'billing_modified_by' => ProNav_Auth::getUserID(),
                'billing_modified_on' => new Zend_Db_Expr('NOW()')
            );

            foreach ($flds as $f){
                $update[$f[1]] = $projectinfo->$f[0];
            }

            if (count($update)>3){ //if you have any updates there will be more than 3 records. 
                Application_Model_Corporations::updateExisting($update);
            } 
        }
    }

    /** End Accounting **/

    /** Acquisition **/

    public function editAcquisitionAction()
    {
        $project_id = $this->_getParam('id');

        $project = Application_Model_Projects::GetProjectInfo($project_id);

        if (!$project->project_id)
        {
            ProNav_Auth::pageNotFound();
        }

        $this->_helper->viewRenderer->setNoRender(false);            
        $this->view->project = $project;
        $this->view->estimators = Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acq_estimator, true);    
        $this->view->salespeople = Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acq_sales_person, true);    
        $this->view->percentages = Application_Model_Projects::getProjectPercentages();
        $this->view->estimating_complete_options = Application_Model_List::getList(
            array('table' => 'project_percentages_estimating',
                'selectedValue' => $project->acq_estimating_percent_complete,
                'idKey' => 'project_estimating_id',
                'special_fields' => array('value'))
        );
        $this->view->building_types = Application_Model_Projects::getBuildingTypes();

    }

    public function commitAcquisitionAction()
    {
        $data = Zend_Json::decode($this->_getParam('data'));

        ProNav_Utils::WhiteListArray($data,array('project_id','acq_sales_person','acq_estimator','acq_probability',
            'acq_pre_bid_mandatory','acq_pre_bid_date','acq_bid_date','acq_booking_month','acq_booking_year','acq_building_type',
            'acq_device_count','acq_sq_footage','acq_comment', 'acq_estimating_percent_complete', 'acq_estimating_hours', 
            'acq_bid_review_meeting', 'acq_bid_descope_meeting', 'acq_job_turnover_meeting'));

        $data = ProNav_Utils::stripTagsAndTrim($data);
        $pre_bid_mandatory = $data['acq_pre_bid_mandatory']; //grab this before data is scrubbed. might be 0. 
        ProNav_Utils::scrubArray($data,true);

        $data['project_id'] = (is_numeric($data['project_id']) ? $data['project_id'] : -1);

        $data['acq_sales_person'] = (is_numeric($data['acq_sales_person']) ? $data['acq_sales_person'] : -1);
        $data['acq_estimator'] = (is_numeric($data['acq_estimator']) ? $data['acq_estimator'] : -1);
        $data['acq_probability'] = (is_numeric($data['acq_probability']) ? $data['acq_probability'] : -1);

        $data['acq_pre_bid_mandatory'] = ($pre_bid_mandatory == '1' || $pre_bid_mandatory == '0') ?  $pre_bid_mandatory : new Zend_Db_Expr('NULL');
        $data['acq_pre_bid_date'] = ProNav_Utils::toMySQLDate($data['acq_pre_bid_date'],true);
        $data['acq_bid_date'] = ProNav_Utils::toMySQLDate($data['acq_bid_date']);
        $data['acq_bid_review_meeting'] = ProNav_Utils::toMySQLDate($data['acq_bid_review_meeting'],true);

        $data['acq_booking_month'] = (is_numeric($data['acq_booking_month']) && $data['acq_booking_month'] >= 0 ? $data['acq_booking_month'] : new Zend_Db_Expr('NULL'));
        $data['acq_booking_year'] = (is_numeric($data['acq_booking_year']) && $data['acq_booking_year'] >= 0 ? $data['acq_booking_year'] : new Zend_Db_Expr('NULL'));

        $data['acq_building_type'] = (is_numeric($data['acq_building_type']) ? $data['acq_building_type'] : -1);
        $data['acq_sq_footage'] = (is_numeric($data['acq_sq_footage']) ? $data['acq_sq_footage'] : new Zend_Db_Expr('NULL'));
        $data['acq_device_count'] = (is_numeric($data['acq_device_count']) ? $data['acq_device_count'] : new Zend_Db_Expr('NULL'));

        $data['acq_estimating_hours'] = (is_numeric($data['acq_estimating_hours']) ? $data['acq_estimating_hours'] : new Zend_Db_Expr('NULL'));
        $data['acq_estimating_percent_complete'] = (is_numeric($data['acq_estimating_percent_complete']) ? $data['acq_estimating_percent_complete'] : new Zend_Db_Expr('NULL'));

        $data['acq_bid_descope_meeting'] = ProNav_Utils::toMySQLDate($data['acq_bid_descope_meeting']);
        $data['acq_job_turnover_meeting'] = ProNav_Utils::toMySQLDate($data['acq_job_turnover_meeting']);

        $data['acq_modified_by'] = ProNav_Auth::getUserID();
        $data['acq_modified_date'] = new Zend_Db_Expr('NOW()');

        $result = Application_Model_Projects::updateProject($data);
        if ($result == 0) {
            echo 0;
        } else {
            $project = Application_Model_Projects::GetProjectInfo($data['project_id']);
            echo $this->view->partial('project/acquisition.phtml',array('project' => $project));            
        }
    }

    /** End Acquisition **/

    /** RFI **/

    public function viewRfiAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam("id",-1);
        $rfi_id = $this->_getParam('rfi_id', 0);

        $rfi = new Application_Model_RFI();
        $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
        $rfi = $rfis[$rfi_id];

        $this->view->rfi = $rfi;


        if(!is_null($rfi->modified_by)){
            $this->view->updated_by = $rfi->ModifiedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($rfi->modified_date);
        }else if(!is_null($rfi->created_by)){
            $this->view->updated_by = $rfi->CreatedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($rfi->created_date);
        }
    }

    public function editRfiAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam('id', -1);
        $rfi_id = $this->_getParam('rfi_id', 0);

        $rfi = new Application_Model_RFI();
        if($rfi_id){ //edit
            $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
            $rfi = $rfis[$rfi_id];
            $this->view->to_user_selected = $rfi->sent_to;
        }else{  //add
            $rfi->rfi_num = Application_Model_RFIs::getNextRFINum($project_id);
            $rfi->cost_impact = Application_Model_RFI::IMPACT_UNSURE;
            $rfi->schedule_impact = Application_Model_RFI::IMPACT_UNSURE;
            $rfi->date_submitted = new Zend_Date();
            $this->view->to_user_selected = Application_Model_RFIs::lastSentToPerson($project_id);
        }

        $this->view->obj_type = Application_Model_File::OBJ_RFI;     
        $this->view->max_file_size = ProNav_Utils::getMaxFileUploadSize();       
        $this->view->rfi = $rfi;  

        $this->view->from_users = $this->projectMembersByType($project_id, 1);
        $this->view->to_users = $this->projectMembersByType($project_id, 2);
        $this->view->from_user_selected = is_null($rfi->sent_by) ? ProNav_Auth::getUserID() : $rfi->sent_by;

        $this->view->impact_responses = Application_Model_RFIs::getImpactResponses();

        if(!is_null($rfi->modified_by)){
            $this->view->updated_by = $rfi->ModifiedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($rfi->modified_date);
        }else if(!is_null($rfi->created_by)){
            $this->view->updated_by = $rfi->CreatedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($rfi->created_date);
        }
    }

    public function updateRfiAction()
    {
        $this->_helper->viewRenderer->setNoRender(false); 
        $project_id = $this->_getParam('id', -1);
        $rfi_id = $this->_getParam('rfi_id', 0);

        $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
        $rfi = $rfis[$rfi_id];
        if(!$rfi->response_date)
            $rfi->response_date = new Zend_Date();

        $this->view->to_users = $this->projectMembersByType($project_id, 2);
        $this->view->obj_type = Application_Model_File::OBJ_RFI; 
        $this->view->max_file_size = ProNav_Utils::getMaxFileUploadSize();
        $this->view->rfi = $rfi;

        if(!is_null($rfi->modified_by)){
            $this->view->updated_by = $rfi->ModifiedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($rfi->modified_date);
        }else if(!is_null($rfi->created_by)){
            $this->view->updated_by = $rfi->CreatedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($rfi->created_date);
        }

    }

    public function commitUpdateRfiAction()
    {
        /*
        Note - though there is an action email sent for RFI Updated, it is done
        via the rfisAction b/c it can't be sent until after files have been saved so they can be included in the email.
        */

        $project_id = $this->_getParam("id", -1);
        $rfi_id = $this->_getParam("rfi_id", 0);
        $data = Zend_Json::decode($this->_getParam("data", array()));

        $jOut = array();
        $errors = array();

        $data = ProNav_Utils::stripTagsAndTrim($data);
        $data['response_date'] = ProNav_Utils::toMySQLDate($data['response_date']);

        $data['modified_by'] = ProNav_Auth::getUserID();
        $data['modified_date'] = new Zend_Db_Expr('NOW()');
        $u = Application_Model_RFIs::updateRFI($rfi_id, $data);
        if(!$u)
            $errors[] = "No rows updated.";

        $jOut["rfi_id"] = $rfi_id;
        $jOut["errors"] = $errors;

        echo Zend_Json::encode($jOut);
    }

    public function rfiFilesAction()
    {
        $project_id = $this->_getParam("id", -1);
        $rfi_id = $this->_getParam("rfi_id", 0);

        $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
        $rfi = $rfis[$rfi_id];
        $files = $rfi->files;

        $sent = array();
        $received = array();

        if(count($files) > 0){
            foreach($files as $file){
                if($file->outgoing)
                    $sent[] = $file;
                else
                    $received[] = $file;
            }
        }

        echo $this->view->partial('project/partial-rfi-files.phtml',
            array(
                'obj_id' => $rfi_id,
                'sent_files' => $sent,
                'received_files' => $received,
                'project_id' => $project_id
            )
        );           

    }

    public function coverLetterAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam("id",-1);
        $submittal_rev_id = $this->_getParam("submittal_rev_id", 0);

        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);
        $this->view->project_title = $projectInfo->title;
        $this->view->project_job_no = $projectInfo->job_no;
        $this->view->project_ref_no = $projectInfo->ref_no;


        $revInfo =  Application_Model_Submittals::GetSubmittalRevInfo($submittal_rev_id);

        $this->view->submittal_rev = $revInfo;
        $this->view->submittal = Application_Model_Submittals::GetSubmittalInfo($revInfo->submittal_id);

        $SentBy = Application_Model_Users::GetUserInfo($revInfo->sent_by);
        if(!is_null($SentBy)){
            $branch_address = $SentBy->Address;
            if(!is_null($branch_address))
                $this->view->branch_address = $branch_address->getFormattedAddress();
            $this->view->sender_name = $SentBy->getFormattedName();
            $this->view->sender_title = $SentBy->title;
            $this->view->sender_email = $SentBy->email;
            $this->view->sender_fax = $SentBy->fax;
            $this->view->sender_phone = $SentBy->phone_office;
        }

        $SentTo = Application_Model_Users::GetUserInfo($revInfo->sent_to);
        if(!is_null($SentTo)){
            $recipient_address = $SentTo->Address;
            if(!is_null($recipient_address))
                $this->view->recipient_address = $recipient_address->getFormattedAddress();
            $this->view->recipient_name = $SentTo->getFormattedName();
            $this->view->recipient_title = $SentTo->title;
            $this->view->recipient_phone = $SentTo->phone_office;
            $this->view->recipient_fax = $SentTo->fax;
            $this->view->recipient_email = $SentTo->email;
        }

    }

    public function rfiLetterAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam("id", -1);
        $rfi_id = $this->_getParam("rfi_id", 0);

        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);
        $this->view->project_title = $projectInfo->title;
        $this->view->project_job_no = $projectInfo->job_no;
        $this->view->project_ref_no = $projectInfo->ref_no;

        $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
        $rfi = $rfis[$rfi_id];
        $this->view->rfi = $rfi;

        $SentBy = Application_Model_Users::GetUserInfo($rfi->sent_by);
        if(!is_null($SentBy)){
            $branch_address = $SentBy->Address;
            if(!is_null($branch_address))
                $this->view->branch_address = $branch_address->getFormattedAddress();
            $this->view->sender_name = $SentBy->getFormattedName();
            $this->view->sender_title = $SentBy->title;
            $this->view->sender_email = $SentBy->email;
            $this->view->sender_fax = $SentBy->fax;
            $this->view->sender_phone = $SentBy->phone_office;
        }

        $SentTo = Application_Model_Users::GetUserInfo($rfi->sent_to);
        if(!is_null($SentTo)){
            $recipient_address = $SentTo->Address;
            if(!is_null($recipient_address))
                $this->view->recipient_address = $recipient_address->getFormattedAddress();
            $this->view->recipient_name = $SentTo->getFormattedName();
            $this->view->recipient_title = $SentTo->title;
            $this->view->recipient_phone = $SentTo->phone_office;
            $this->view->recipient_fax = $SentTo->fax;
            $this->view->recipient_email = $SentTo->email;
        }

        $ResponseBy = Application_Model_Users::GetUserInfo($rfi->response_by);
        if(!is_null($ResponseBy)){
            $this->view->response_by_name = $ResponseBy->getFormattedName();
        }

    }

    public function rfisAction()
    {
        $project_id = $this->_getParam('id');
        $event = $this->_getParam('event');
        $rfi_id = $this->_getParam('rfi_id');

        if (($event ==  ProNav_Notification::EVENT_RFI_CREATED || 
        $event == ProNav_Notification::EVENT_RFI_UPDATED) && is_numeric($rfi_id)
        ){
            ProNav_Utils::registerEvent($project_id, $event, $rfi_id, ProNav_Auth::getUserID(), 
                ProNav_Utils::toMySQLDate(new Zend_Date())
            );
        }

        $rfis = Application_Model_RFIs::getRFIsForProject($project_id);
        echo $this->view->partial('project/partial-rfis.phtml', array('rfis' => $rfis));
    }

    public function commitRfiAction()
    {
        /*
        Note - though there is an action email sent for RFI created, it is done
        via the rfisAction b/c it can't be sent until after files have been saved so they can be included in the email.
        */
        $project_id = $this->_getParam("id", -1);
        $rfi_id = $this->_getParam("rfi_id", 0);
        $data = Zend_Json::decode($this->_getParam("data", array()));

        $jOut = array();
        $errors = array();

        $data = ProNav_Utils::stripTagsAndTrim($data);
        $data['project_id'] = $project_id;
        $data['date_submitted'] = ProNav_Utils::toMySQLDate($data['date_submitted']);
        $data['date_required'] = ProNav_Utils::toMySQLDate($data['date_required']);

        if($rfi_id == 0){
            $data['created_by'] = ProNav_Auth::getUserID();
            $data['created_date'] = new Zend_Db_Expr('NOW()');
            $rfi_id = Application_Model_RFIs::createRFI($data);
            if(!$rfi_id)
                $errors[] = "Error creating RFI.";
        }else{
            $data['modified_by'] = ProNav_Auth::getUserID();
            $data['modified_date'] = new Zend_Db_Expr('NOW()');
            $u = Application_Model_RFIs::updateRFI($rfi_id, $data);
            if(!$u)
                $errors[] = "No rows updated.";
        }


        $jOut["rfi_id"] = $rfi_id;
        $jOut["errors"] = $errors;

        echo Zend_Json::encode($jOut);

    }

    /** End RFI **/

    /** Submittals **/

    public function viewSubmittalAction()
    {

        $project_id = $this->_getParam("id",-1);
        $submittal_id = $this->_getParam("submittal_id", 0);

        $this->_helper->viewRenderer->setNoRender(false);
        $submittals = Application_Model_Submittals::getSubmittalsForProject($project_id);
        $submittal  =  $submittals[$submittal_id];
        $this->view->submittal = $submittal;

        if(!is_null($submittal->modified_by)){
            $this->view->updated_by = $submittal->ModifiedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($submittal->modified_date);
        }else{
            $this->view->updated_by = $submittal->CreatedBy->bclink();
            $this->view->updated_date = ProNav_Utils::formatTimeStamp($submittal->created_date);
        }
    }

    public function commitSubmittalAction()
    {
        $project_id = $this->_getParam("id",-1);
        $submittal_id = $this->_getParam("submittal_id", 0);
        $data = Zend_Json::decode($this->_getParam('data',array()));
        $jOut = array();
        $errors = array();

        $data = ProNav_Utils::stripTagsAndTrim($data);
        ProNav_Utils::scrubArray($data, true);
        $data['project_id'] = $project_id;
        $data['date_required'] = ProNav_Utils::toMySQLDate($data['date_required']);
        if($submittal_id == 0){
            $data["created_by"] = ProNav_Auth::getUserID();
            $data["created_date"] = new Zend_Db_Expr('NOW()'); 
            $n = Application_Model_Submittals::createSubmittal($data);
            if(!$n)
                $errors[] = "Error creating submittal.";
        }else{ 
            $data["modified_by"] = ProNav_Auth::getUserID();
            $data["modified_date"] = new Zend_Db_Expr('NOW()'); 
            $u = Application_Model_Submittals::updateSubmittal($submittal_id, $data);
            if(!$u)
                $errors[] = "No rows updated.";
        }

        $jOut["message"] =   $this->view->partial('project/partial-submittals.phtml', array('submittals' => Application_Model_Submittals::getSubmittalsForProject($project_id)));
        $jOut["errors"] = $errors;
        echo Zend_Json::encode($jOut); 

    }

    public function editSubmittalAction()
    {
        $project_id = $this->_getParam("id",-1);
        $submittal_id = $this->_getParam("submittal_id", 0);

        $this->_helper->viewRenderer->setNoRender(false);
        $submittalinfo =  Application_Model_Submittals::GetSubmittalInfo($submittal_id);
        if(!$submittal_id) $submittalinfo->submittal_num = Application_Model_Submittals::getNextSubmittalNumber($project_id);
        $this->view->submittalinfo =  $submittalinfo;
        $this->view->leadWarning =  Application_Model_Submittal::leadTimeWarning($submittalinfo->date_required, $submittalinfo->lead_time);
    }

    public function editSubmissionAction()
    {
        $submittal_id = $this->_getParam("submittal_id", 0);
        $submittal_rev_id = $this->_getParam("submittal_rev_id", 0);
        $project_id = $this->_getParam("id",-1);

        $this->_helper->viewRenderer->setNoRender(false);
        $revInfo =  Application_Model_Submittals::GetSubmittalRevInfo($submittal_rev_id);
        if($submittal_rev_id == 0){
            $revInfo->rev_num =  Application_Model_Submittals::getLastRevNum($submittal_id) + 1;
            $this->view->to_user_selected = Application_Model_Submittals::lastSentToPerson($project_id);
        }else{
            $this->view->to_user_selected = $revInfo->sent_to;
        }

        if(is_null($revInfo->date_returned))
            $revInfo->date_returned = new Zend_Date();
        $this->view->isNew = $submittal_rev_id == 0;
        $this->view->submittalrevinfo = $revInfo;
        $this->view->submittal_rev_methods = Application_Model_Submittals::getSubmittalRevMethods();
        $this->view->submittal_rev_status = Application_Model_Submittals::getSubmittalRevStatus(true);
        $this->view->obj_type =  Application_Model_File::OBJ_SUBMITTAL;

        $this->view->from_users = $this->projectMembersByType($project_id, 1);
        $this->view->to_users = $this->projectMembersByType($project_id, 2);

        $this->view->from_user_selected = is_null($revInfo->sent_by) ? ProNav_Auth::getUserID() :   $revInfo->sent_by;

    }

    public function refreshSubmittalsAction()
    {
        $project_id =  $this->_getParam("id",-1);
        echo $this->view->partial('project/partial-submittals.phtml', array('submittals' => Application_Model_Submittals::getSubmittalsForProject($project_id)));
    }

    public function commitSubmissionAction()
    {
        $project_id = $this->_getParam("id",-1);
        $submittal_id = $this->_getParam("submittal_id", 0);
        $submittal_rev_id = $this->_getParam("submittal_rev_id", 0);
        $data = Zend_Json::decode($this->_getParam('data',array()));
        $jOut = array();         
        $errors = array();   

        $jOut["submittal_rev_id"] = $submittal_rev_id;

        //$data = ProNav_Utils::stripTagsAndTrim($data);
        ProNav_Utils::scrubArray($data, true, false);

        $data['submittal_id'] = $submittal_id;
        if(!is_null($data['date_submitted'])) 
            $data['date_submitted'] = ProNav_Utils::toMySQLDate($data['date_submitted']);
        if(!is_null($data['date_returned']))
            $data['date_returned'] = ProNav_Utils::toMySQLDate($data['date_returned']);

        if($submittal_rev_id == 0){
            if(intval($data['for_approval']) == 1)
                $data['status_id'] = Application_Model_SubmittalStatus::SUBMITTED_FOR_APPROVAL;
            else
                $data['status_id'] = Application_Model_SubmittalStatus::SUBMITTED_FOR_RECORD;  
            $data["created_by"] = ProNav_Auth::getUserID();
            $data["created_date"] = new Zend_Db_Expr('NOW()'); 

            $n = Application_Model_Submittals::createSubmittalRev($data);
            $jOut["submittal_rev_id"] = $n;
            if(!$n)
                $errors[] = "Error creating submission.";
        }else{ 

            $revInfo = Application_Model_Submittals::GetSubmittalRevInfo($submittal_rev_id);

            if(intval($data['for_approval']) == 0) {
                $data['status_id'] = Application_Model_SubmittalStatus::SUBMITTED_FOR_RECORD; 
            }else{
                if(is_null($data['status_id']) && ($revInfo->status_id == Application_Model_SubmittalStatus::SUBMITTED_FOR_RECORD || $revInfo->status_id == Application_Model_SubmittalStatus::NOT_SUBMITTED))
                    $data['status_id'] = Application_Model_SubmittalStatus::SUBMITTED_FOR_APPROVAL;
            }

            $data["modified_by"] = ProNav_Auth::getUserID();
            $data["modified_date"] = new Zend_Db_Expr('NOW()'); 

            $u = Application_Model_Submittals::updateSubmittalRev($submittal_rev_id, $data);
            if(!$u)
                $errors[] = "No rows updated.";
        }

        $jOut["message"] =   $this->view->partial('project/partial-submittals.phtml', array('submittals' => Application_Model_Submittals::getSubmittalsForProject($project_id)));
        $jOut["errors"] = $errors;
        echo Zend_Json::encode($jOut);

    }

    public function submissionFilesAction()
    {
        $project_id = $this->_getParam("id",-1);
        $submittal_rev_id = $this->_getParam("submittal_rev_id", 0);

        echo $this->view->partial('project/partial-submission-files.phtml', 
            array(
                'obj_id' => $submittal_rev_id,
                'sent_files' => Application_Model_Submittals::getSubmittalRevFiles($submittal_rev_id, 1),
                'received_files' => Application_Model_Submittals::getSubmittalRevFiles($submittal_rev_id, 0),
                'project_id' => $project_id
            )
        );
    }

    public function releaseOrderAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);

        $project_id = $this->_getParam("id",-1);
        $submittal_id = $this->_getParam('submittal_id', 0);
        $submittal = Application_Model_Submittals::GetSubmittalInfo($submittal_id);
        $this->view->submittal_header = $submittal->submittal_num." - ".$submittal->title;
        $this->view->date_released = is_null($submittal->date_released) ? ProNav_Utils::formatDate(new Zend_Date(), false) : ProNav_Utils::formatDate($submittal->date_released, false);
        $this->view->from_users = $this->projectMembersByType($project_id, 1);
        $this->view->released_by = is_null($submittal->released_by) ? ProNav_Auth::getUserID() : $submittal->released_by;
    }

    public function commitReleaseOrderAction()
    {
        $jOut = array();
        $errors = array();

        $project_id = $this->_getParam("id",-1);
        $submittal_id = $this->_getParam("submittal_id", 0);
        $data = Zend_Json::decode($this->_getParam('data',array()));
        $data['date_released'] = ProNav_Utils::toMySQLDate($data['date_released']);
        $data["modified_by"] = ProNav_Auth::getUserID();
        $data["modified_date"] = new Zend_Db_Expr('NOW()'); 
        $n = Application_Model_Submittals::releaseOrder($submittal_id, $data);
        if(!$n)
            $errors[] = "Error releasing order.";


        $jOut["message"] =   $this->view->partial('project/partial-submittals.phtml', array('submittals' => Application_Model_Submittals::getSubmittalsForProject($project_id)));
        $jOut["errors"] = $errors;
        echo Zend_Json::encode($jOut); 
    }

    public function leadWarningAction()
    {
        $warning = "";

        $date_required = $this->_getParam("date_required", "");
        $lead_time = $this->_getParam("lead_time", "");
        if(!is_null($date_required) && !is_null($lead_time)){
            $warning = Application_Model_Submittal::leadTimeWarning($date_required, $lead_time);
        }
        echo $warning;
    }

    /** End Submittals **/

    /** Scope **/

    public function loadEditScopeAction()
    {
        $project_id = $this->_getParam('id');
        $scopes = Application_Model_Projects::GetScopes($project_id);
        echo $this->view->partial('project/edit-scope.phtml',array('scopes'=>$scopes));
    }

    public function saveScopeAction()
    {
        $project_id = $this->_getParam('id');
        $data = ProNav_Utils::stripTagsAndTrim(Zend_Json::decode($this->_getParam('data',array())));       
        $jOut = array();
        $errors = array();

        $n = Application_Model_Projects::SaveScope($project_id, $data);    
        if($n){
            $project = Application_Model_Projects::GetProjectRow($project_id);    
            $jOut['message'] = $this->view->partial('project/scope.phtml',array('projectinfo'=>$project));
        }else{
            $errors[] = "No rows updated";
        }     

        $jOut["errors"] = $errors;
        echo Zend_Json::encode($jOut); 
    }

    /** End Scope **/
	
	
	
	/** Material Location **/

    public function loadEditMaterialLocationAction()
    {
        $project_id = $this->_getParam('id');
        $material = Application_Model_Projects::GetMaterialLocation($project_id);
		
        echo $this->view->partial('project/edit-material-location.phtml',array('material'=>$material,'project_id'=>$project_id));
    }

    public function saveMaterialLocationAction()
    {
        $project_id = $this->_getParam('id');
        $data = ProNav_Utils::stripTagsAndTrim(Zend_Json::decode($this->_getParam('data',array())));       
        $jOut = array();
        $errors = array();

        $n = Application_Model_Projects::SaveMaterialLocation($project_id, $data);    
        if($n){
            $project = Application_Model_Projects::GetProjectRow($project_id);    
            $jOut['message'] = $this->view->partial('project/material-location.phtml',array('projectinfo'=>$project));
        }else{
            $errors[] = "No rows updated";
        }     

        $jOut["errors"] = $errors;
        echo Zend_Json::encode($jOut); 
    }

    /**End Material Location **/
	

    /** Project Team **/

    public function getInitialProjectTeamMembersAction() {
        //preempt the notification_queue to get the project teams right away
        $dfc = $this->_getParam('done_for_corporation',-1);
        $dfw = $this->_getParam('done_for_workgroup',-1);
        $dbw = $this->_getParam('done_by_workgroup',-1);
        $dal = $this->_getParam('done_at_location',-1);
        $dlo = $this->_getParam('location_owner',-1);
        $stage = $this->_getParam('stage_id',-1);
        $authorization = $this->_getParam('authorization',-1);

        //set values for clients
        if (!ProNav_Auth::isEmployee()){
            $dfc = ProNav_Auth::getCorporationID();
            $dlo = ProNav_Auth::getCorporationID();
            $depts = ProNav_Auth::getDepartments();
            if (count($depts) < 1){echo 'No User Workgroups'; die;}
            if (!in_array($dfw,$depts)){
                $dfw = $depts[0];
            }
        }

        $errs = array();

        //check values.
        if (!is_numeric($dfc) || $dfc < 1){
            $errs[] = 'Please Select a Customer.';
        }              

        if (!is_numeric($dfw) || $dfw < 1){
            $errs[] = 'Please Select a Workgroup.';
        }

        if (!is_numeric($dlo) || $dlo < 1){
            $errs[] = 'Please Select a Location Owner.';
        }

        if (!is_numeric($dal) || $dal < 1){
            $errs[] = 'Please Select a Location.';
        } else if (!Application_Model_Corporations::hasLocation($dal,$dlo)){
            $errs[] = 'Unknown Location Selected.';            
        }

        if (!is_numeric($dbw) || $dbw < 1){
            $errs[] = 'Please Select a Business Unit.';
        }

        if (!ProNav_Auth::isEmployee() && ($authorization != ProNav_Utils::STAGE_AUTHORIZED && $authorization != ProNav_Utils::STAGE_RFP)){
            $errs[] = 'Please specify whether you are authorizing a time & material project or requesting a proposal.';    
        } else if (!is_numeric($stage) || $stage < 1){
            $errs[] = 'Please Select A Project Stage.';
        }

        if (count($errs)> 0){
            echo Zend_Json::encode(array('state'=>0,'msgs'=>$errs));
            die;
        }

        //get the list of all users for the given corporation (we will remove some later). 
        //put them in the format expected by the ajax call.

        $offUsers = Application_Model_Projects::getProjectTeamAvailable(-1,$dfc,$dfw);
        $offUsers_2 = array();
        foreach ($offUsers as $user){
            $offUsers_2[$user->user_id] = array(
                "user_id" => $user->user_id,
                "name" => $user->getFormattedName(Application_Model_User::LFMI),
                "corporation_id" => $user->Corporation->corporation_id,
                "corporation" => $user->Corporation->name,
                "assigned" => 0,
                "pronav_access" => $user->pronav_access

            );
        }


        //dip into the notification queue for the list of subscribers.
        //This code here was a refactoring - which is why we are dipping into the notificaton queue. 
        $np = new ProNav_NotificationProject();
        $queue = new stdClass();
        $queue->event_type = ProNav_Notification::EVENT_PROJECT_CREATED; 
        $queue->event_value = $stage;
        $queue->only_action = ProNav_NotificationProject::ACTION_ADD_TO_TEAM;
        $queue->done_for_corporation = $dfc;
        $queue->done_for_workgroup = $dfw;
        $queue->done_by_workgroup = $dbw;
        $queue->done_at_location = $dal;
        $queue->location_owner = $dlo;        
        $np->setQueue($queue);

        $onUsers = $np->getSubscribers(true);
        $onUsers_2 = array();
        foreach ($onUsers as $user){
            if (!ProNav_Auth::isEmployee() && $user->corporation_id == ProNav_Utils::TriMId){continue;} 

            $onUsers_2[$user->user_id] = array(
                "user_id" => $user->user_id,
                "name" => ($user->lastname . ', ' . $user->firstname),
                "corporation_id" => $user->corporation_id,
                "corporation" => $user->name,
                "assigned" => 1,
                "pronav_access" => $user->pronav_access
            );

            //remove the user from the offUsers list, if they are found in the subscribers list. 
            unset($offUsers_2[$user->user_id]);            
        }

        //add the current user to the onUsers list.
        $currentUser = Application_Model_Users::getUser(ProNav_Auth::getUserID());
        $onUsers_2[] = array(
            'user_id' => $currentUser->user_id,
            'name' => $currentUser->getFormattedName(Application_Model_User::LFMI),
            'corporation_id' => $currentUser->corporation_id,
            'corporation' => $currentUser->corporation_name,
            'assigned' => 1,
            'pronav_access' => $currentUser->pronav_access
        );

        echo Zend_Json::encode(array('state'=>1,'users'=>array_merge($onUsers_2,$offUsers_2)));
    }

    public function editProjectTeamAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);
        $this->_helper->layout->disableLayout(); 

        $projectId = $this->_getParam('id');
        $this->view->corporations = Application_Model_Corporations::GetAllCorporations();
        $this->view->project_id = $projectId;
        $this->view->isEmployee = ProNav_Auth::isEmployee();

        $billToCorpId = Application_Model_Projects::getProjectBillToCorporationId($projectId);
        foreach ($this->view->corporations as $corp){
            if ($corp->corporation_id == $billToCorpId){
                $this->view->billToCorp = $corp;
                break;
            }
        } 

    }

    public function assignProjectTeamAction()
    {
        $userList = Zend_Json::decode($this->_getParam('user_list'));
        if (!is_array($userList)){
            $userList = array();
        }

        $projectId = $this->_getParam('id');

        $badUsers = array(); //These are user_ids that will be dropped for not meeting a condition below.

        $requested_users = Application_Model_Users::getUsers(array_keys($userList));
        $project = Application_Model_Projects::GetProjectInfo($projectId);  

        //When saving the team all users are sent back. 
        //However, users are permissioned as to what kinds of users they can add. 
        //Since they whole team is sent back, we simply strip out any users that they are not allowed to manipulate.
        //This will then allows them to only perform actions on the users that they CAN manipulate. 
        //No errors are generated.
        foreach ($userList as $user_id => $action){

            if (ProNav_Auth::isEmployee()){
                //Employess

                if ($action == '0' && !ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_TEAM_REMOVE_USERS)){
                    //Removing users is a permission.
                    $badUsers[] = $user_id;                    
                } else {
                    $isTriMUser = $requested_users[$user_id]->corporation_id == ProNav_Utils::TriMId;
                    $isCorpUser = $requested_users[$user_id]->corporation_id == $project->done_for_corporation;
                    $isOtherUser = !$isTriMUser && !$isCorpUser;

                    if ($isTriMUser && !ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_TEAM_ADD_TRIM)){
                        $badUsers[] = $user_id;                        
                    } else if ($isCorpUser && !ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_TEAM_ADD_CUSTOMERS)){
                        $badUsers[] = $user_id;                                                
                    } else if ($isOtherUser && !ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_TEAM_ADD_OTHERS)){
                        $badUsers[] = $user_id;                        
                    }
                }   
            } else {
                //Non employees canot add users outside of the projects done for workgroup. 
                if (!in_array($project->done_for_workgroup, $requested_users[$user_id]->workgroups)){
                    $badUsers[] = $user_id;                    
                }    
            }
        }   

        //strip out all users that should be ignored because of permission violations. 
        foreach ($badUsers as $user_id){
            unset($userList[$user_id]);
        }

        Application_Model_Projects::setProjectTeamAssignments($projectId, $userList);
        $projectTeam = Application_Model_Projects::getProjectTeam($projectId);

        //I return a JSON because I need to do two things
        //1. Update the users table with a new table. 
        //2. Update the "Add/Remove Me" link to the appropriate value. 
        $output = array(
            'table' => $this->view->partial('project/project-team.phtml', array('users' => $projectTeam)),
            'userIsOnTeam' => array_key_exists(ProNav_Auth::getUserID(), $projectTeam)        
        );
        echo Zend_Json::encode($output);        
    }

    public function assignUserAction()
    {
        if (ProNav_Auth::isEmployee() && !ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_TEAM_ADD_REMOVE_ME)){
            ProNav_Auth::unauthorized($this);
        }

        $projectId = $this->_getParam('id');
        $userAction = $this->_getParam('user_action');
        $userList = array(ProNav_Auth::getUserID() => $userAction);
        Application_Model_Projects::setProjectTeamAssignments($projectId, $userList);
        $projectTeam = Application_Model_Projects::getProjectTeam($projectId);
        echo $this->view->partial('project/project-team.phtml',array('users' => $projectTeam));
    }

    public function setFavoriteAction(){
        $project_id = $this->_getParam('project_id');
        $set = ($this->_getParam('set') == "1" ? true : false);        
        $status = array('status' => 0);               
        if (!is_numeric($project_id)){
            $status['error'] = 'No Project ID given.';    
        } else {
            Application_Model_Projects::setFavorite($project_id, $set);    
            $status['status'] = 1;
        }                         
        $this->getResponse()->setBody(Zend_Json::encode($status));   
    }

    public function getCorporationUnassignedUsersAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout(); 
        $corpId = $this->_getParam('corporation_id');
        $projectId = $this->_getParam('id');

        $users = Application_Model_Projects::getProjectTeamAvailable($projectId,$corpId);

        $ary = array();
        foreach ($users as $user)
        {
            $ary[$user->user_id] = array(
                "user_id" => $user->user_id,
                "name" => $user->getFormattedName(Application_Model_User::LFMI),
                "corporation_id" => $user->Corporation->corporation_id,
                "corporation" => $user->Corporation->name,
                "assigned" => 0
            );
        }
        echo Zend_Json::encode($ary);
    }

    public function getCorporationAssignedUsersAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout(); 
        $projectId = $this->_getParam('id');
        $users = Application_Model_Projects::getProjectTeamAssigned($projectId);
        $ary = array();
        foreach ($users as $user)
        {
            $ary[$user->user_id] = 
            $optionItem = array(
                "user_id" => $user->user_id,
                "name" => $user->getFormattedName(Application_Model_User::LFMI),
                "corporation_id" => $user->Corporation->corporation_id,
                "corporation" => $user->Corporation->name,
                "assigned" => 1
            );
        }
        echo Zend_Json::encode($ary);
    }

    public function teamMembersAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $project_id = $this->_getParam('project_id');
        $projectTeam = Application_Model_Projects::getProjectTeam($project_id);
        echo $this->view->partial('project/project-team.phtml',
            array('users' => $projectTeam, 'suffix' => '2', 'includeLastLogin'=>false));
    }

    /** End Project Team **/
                                                                                                              
    /** Schedule **/

    public function editScheduleAction()
    {
        $project_id = $this->_getParam('id');
        $this->view->projectinfo = Application_Model_Projects::GetProjectRow($project_id);
        $this->_helper->viewRenderer->setNoRender(false);
        $this->_helper->layout->disableLayout(false); 
    }

    public function commitScheduleAction()
    {
        $data = Zend_Json::decode($this->_getParam('data'));
        $project_id = $data["project_id"];

        //convert the dates to valid mysql formats (they come in like "Tue 10/27/2010")
        foreach ($data as $key=>$value)
        {
            if ($key == "project_id" || $value == ""){continue;}
            $data[$key] = ProNav_Utils::toMySQLDate($value);            
        }
        $data["schedule_modified_by"] = ProNav_Auth::getUserID();
        $data["schedule_modified"] = new Zend_Db_Expr('NOW()');

        ProNav_Utils::scrubArray($data,true);

        if (Application_Model_Projects::updateProject($data))
        {
            $projectinfo = Application_Model_Projects::GetProjectInfo($project_id);
            echo $this->view->partial('project/schedule.phtml',array('projectinfo'=>$projectinfo));           
        }
    }

    /** End Schedule **/

    /** Material Location **/

    public function materialLocationEditAction(){

        $project_id -= $this->_getParam('project_id');

        $project = Application_Model_Projects::GetProjectInfo($project_id); //return value is Application_Model_Project

        /* Database Fields
            material_location - nvarchar(max)
            material_location_last_modified_by int
            material_location_last_modified_date datetime
        */
        
        
        // material-location-view.phtml
        // material-location-edit.phtml

        /*
        <div id="material-location-module" class="view | edit">
            <div>Title</div>
            <div class="menu-actions">
                <a href="javascript:void(0);" onclick="pronav.project.material_location.edit.save();">Save</a>
                <a>Cancel</a>
            </div>
            <div class="material-location-content">
                ...
            </div> 
        </div> 
        <script>
            $('#selector").on('click', pronav.project.material_location.edit.save);
        </script>
        */


        /*
        $this->_helper->layout->disableLayout(false); 
        $this->_helper->viewRenderer->setNoRender(false);


        $this->getResponse()->setBody(
        $this->view->partial('project/schedule.phtml', array('projectinfo'=>$projectinfo))
        );
        */           

        //multiple words must be camel cased and then the url structure looks like :   
        //  pronav/material-location-edit/
        // application/scripts/views/projects/material-location-edit.phtml

    }

    public function materialLocationCommitAction(){

        $this->_helper->layout->disableLayout(false); 
        $this->_helper->viewRenderer->setNoRender(false);
        //multiple words must be camel cased and then the url structure looks like :   
        //  pronav/material-location-commit/

        $project_id -= $this->_getParam('project_id');

        Application_Model_Projects::updateProject(array(
            'project_id' => $project_id, 
            'material_location' => 'field data',
            'material_location_last_modified_by' => ProNav_Auth::getUserID(),
            'material_location_last_modified_date' => new Zend_Expr('NOW()')  
            )
        );


    }


    /** End Material Location **/


    /** General Utility Methods **/

    public function getUsersForDeptAction()
    {
        $workgroup_id = $this->_getParam('workgroup_id');
        $o = array();
        $o['users'] = $this->getUsersForDept($workgroup_id,'LFMI');        
        echo Zend_Json::encode($o);
    }

    public function getUsersForCorpAction()
    {
        $corporation_id = $this->_getParam('corporation_id');
        $o = array();
        $o['users'] = $this->getUsersForCorp($corporation_id);        
        echo Zend_Json::encode($o);
    }

    public function getCorpWorkgroupsUsersLocationsAction(){
        $done_for_corporation = $this->_getParam('done_for_corporation');

        $o = array();
        $o['workgroups'] = $this->getWorkgroupsForCorp($done_for_corporation);
        $o['users'] = $this->getUsersForCorp($done_for_corporation);
        $o['locations'] = $this->getLocationsForCorp($done_for_corporation);
        $o['billing'] = Application_Model_Corporations::getBillingInfo($done_for_corporation);

        echo Zend_Json::encode($o);
    }

    public function getCorpWorkgroupsLocationsAction()
    {
        $corporation_id = $this->_getParam('list_corporation');
        echo Zend_Json::encode($this->getCorpWorkgroupsLocations($corporation_id));
    }

    public function getLocationsForCorpAction()
    {
        $corporation_id = $this->_getParam('corporation_id');
        echo $this->getLocationsForCorp($corporation_id);
    }

    public function projectStageEditorAction()
    {
        $this->_helper->viewRenderer->setNoRender(false);
        $project_id = $this->_getParam('id',0);
        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);
        $this->view->project_id = $project_id;
        $this->view->current_stage = $projectInfo->stage_title;
        $this->view->current_stage_id = $projectInfo->stage_id;
        $this->view->stages = Application_Model_Projects::GetStages(ProNav_Auth::isEmployee());
    }   

    public function loadClientStageAction()
    {
        $project_id = $this->_getParam('id',-1);
        $to_stage = $this->_getParam('to_stage',-1);        
        $from_stage = $this->_getParam('from_stage',-1);

        $project = Application_Model_Projects::GetProjectInfo($project_id); 
        $this->view->project = $project; 
        $this->view->to_stage = $to_stage;
        $this->view->from_stage = $from_stage;
        $this->view->states = Application_Model_USStates::getStateList(true);
        $this->view->billTypes = Application_Model_Projects::GetBillTypes(true,false);    
        $this->view->invoiceTypes = Application_Model_Projects::getInvoiceTypes();
        $this->view->customers = Application_Model_Corporations::GetAllCorporations();
        $this->view->workgroups = Application_Model_Workgroups::getCorporationWorkgroups($project->done_for_corporation);
        $this->view->contacts = Application_Model_Users::getCompanyUsers($project->done_for_corporation, false, $project->point_of_contact);
        $this->view->project_types = Application_Model_List::getList('project_types', $project->acct_project_type);
        $this->view->invoice_types = Application_Model_List::getList(array(
            'idKey' => 'project_invoice_type_id',
            'displayKey' => 'name',
            'sortKey'=> 'display_order',
            'table' => 'project_invoice_types'
        ));

        if ($from_stage == ProNav_Utils::STAGE_HOLD)
        {
            //when releasing from hold, provide the list of stage options to send the project to.
            //You should use the stage in which the project was in prior to being on hold.
            //unless that stage is 'cancelled', in which case, you will default to the 'open' stage. 
            $this->view->project_stages = Application_Model_Projects::GetStages(!ProNav_Auth::isEmployee());
            $original_stage = Application_Model_Projects::getPrevStageInfo($project_id)->prev_stage_id;
            $this->view->toStage = $original_stage == ProNav_Utils::STAGE_CANCELLED ? ProNav_Utils::STAGE_OPEN : $original_stage;
        }

        if ($from_stage == ProNav_Utils::STAGE_AUTHORIZED){
            $this->view->project_managers = Application_Model_Users::getCompanyUsers(ProNav_Utils::TriMId, false, $project->acct_project_manager, false);
        }

        echo $this->view->render('project/partial-stage-confirmation.phtml');
    }

    public function otherProjectsAction()
    {
        $this->_helper->viewRenderer->setNoRender();
        $done_at_location  = $this->_getParam('done_at_location');
        $project_id = $this->_getParam('project_id');
        $type = $this->_getParam('type');
        if($type == 'active')
            $projects = Application_Model_Projects::ActiveProjectsAtLocation($done_at_location, $project_id);
        else
            $projects = Application_Model_Projects::OpenProjectsAtLocation($done_at_location, $project_id); 
        echo $this->view->partial('project/partial-open-projects.phtml', array('projects' => $projects, 'type' => $type));
    }

    public function fetchCorpUsersAction(){
        $corp_id = Zend_Filter_Digits::filter($this->_getParam('corporation_id',0));
        //get the list of all users for the given corporation (we will remove some later). 
        //put them in the format expected by the ajax call.
        $users = Application_Model_Projects::getProjectTeamAvailable(-1,$corp_id);
        $userObjects = array();
        foreach ($users as $user)
        {
            $userObjects[$user->user_id] = array(
                "user_id" => $user->user_id,
                "name" => $user->getFormattedName(Application_Model_User::LFMI),
                "corporation_id" => $user->Corporation->corporation_id,
                "corporation" => $user->Corporation->name,
                "assigned" => 0,
                "pronav_access" => $user->pronav_access
            );
        }
        echo Zend_Json::encode(array('state'=>1,'users'=>$userObjects));
    }

    public function projectCountsAction()
    {
        $filter = Zend_Json::decode($this->_getParam('data'));
        $rows = Application_Model_Projects::GetProjectCountsByStage($filter);
        $isClient = !ProNav_Auth::isEmployee();

        $jOut = array();

        $s = '';
        $count = 0;
        $project_id = 0;
        if($rows){
            foreach($rows as $row){
                if($isClient && ($row->stage_id == ProNav_Utils::STAGE_POTENTIAL || $row->stage_id == ProNav_Utils::STAGE_CANCELLED)){
                    //skip
                }else{
                    $count += $row->cnt;
                    $title = str_replace('Potential Project', 'Potential Projects', $row->title);
                    $s .= '<div class="project-list">';
                    $title .= ' ('.$row->cnt.')';
                    $s .= $row->cnt > 0 ? '<a class="stage-has-projects-'.$row->stage_id.'" style="font-weight:bold;outline:none" href="javascript:void(0)" onclick="project.list.toggleProjectStage('.$row->stage_id.')">'.$title.'</a>' : '<span style="color:#999;">'.$title.'</span>';
                    $s .= '</div>';
                    $s .= '<div class="project-list-stage" id="stage-'.$row->stage_id.'" style="display:none;"></div>';
                    if($row->min_id)
                        $project_id = $row->min_id;
                }
            }
            if($count == 1 && $filter['keyword'] != ""){
                $jOut['status'] = 1;
                $jOut['html'] =  $project_id;
            }else{
                $jOut['status'] = 0;
                $jOut['html'] = $s;
            }
        }else{
            $jOut['status'] = 0;
            $jOut['html'] = '<div style="margin:10px;font-weight:bold;">No project found using the criteria specified above.</div>';
        }                    

        echo Zend_Json::encode($jOut);

    }

    public function searchResultsAction()
    {
        $q = $this->_getParam('q');
        $filter['keywords'] = $q;
        $this->view->projects = Application_Model_Projects::GetAllProjects($filter, false);
        $this->view->q = $q;

    }

    public function saveFilterAction()
    {
        $filter = Zend_Json::decode($this->_getParam('data'));

        $whiteList = array('done_for_corporation', 
            'done_for_workgroup',
            'done_at_location',
            'location_owner',
            'done_by_workgroup',
            'point_of_contact',
            'my_watch_list'
        );

        //remove any unwanted keys. 
        ProNav_Utils::WhiteListArray($filter,$whiteList);

        //clean any value that isn't numeric
        foreach ($filter as $key => $value)
        {
            if (!is_numeric($value))
            {
                $filter[$key] = 0;
            }
        }

        //$filterSession = new Zend_Session_Namespace('ProNav_Filter');            
        //$filterSession->filter = Zend_Json::encode($filter);

        echo Application_Model_Projects::SaveFilter($filter);
    }

    public function listProjectsAction()
    {
        $filter = Zend_Json::decode($this->_getParam('data'));
        $this->view->filter = $filter;
        $this->view->projects = Application_Model_Projects::GetAllProjects($filter);
        if (ProNav_Auth::isEmployee())
        {
            $this->renderScript('/project/index-display-t.phtml'); 
        }
        else
        {
            $this->renderScript('/project/index-display-c.phtml'); 
        }
    }

    /** End General Utility Methods **/

    /* Internal Methods */

    private function getNotesModule($project_id){
        $projectInfo = Application_Model_Projects::GetProjectInfo($project_id);
        $projectNotes = Application_Model_Notes::getNotes(Application_Model_Note::PROJECT, $project_id);            
        return $this->view->partial('project/partial-project-notes.phtml', array(
            'projectinfo' => $projectInfo,
            'hasProjectNotes' => !empty($projectNotes),
            'projectNotes' => $this->view->partial('note/note-project-stack.phtml', array("notes" => $projectNotes))
        ));
    }

    private function projectMembersByType($project_id, $type)
    {
        $project_team = Application_Model_Projects::getProjectTeam($project_id);
        $project_team[] = Application_Model_Users::getUser(ProNav_Auth::getUserID());
        $project_team = array_unique($project_team); 

        $to_users = array();
        $from_users = array();

        foreach($project_team as $user){
            $u = new stdClass();
            $u->user_id = $user->user_id;
            $u->name = $user->getFormattedName(Application_Model_User::LFMI);

            if($user->corporation_id == ProNav_Utils::TriMId) {
                $from_users[] = $u;
            }else{
                $to_users[] = $u;
            }
        }

        if($type == 1)
            return $from_users;
        else
            return $to_users;
    }

    private function validateNew($data)
    {
        $errors = array();

        if(!$data['done_for_corporation']){
            $errors[] = "Paying corporation is a required field.";
        }

        if(!$data['done_at_location']) {
            $errors[] = "Location is a required field.";
        }

        if(!$data['done_by_workgroup']){
            $errors[] = ProNav_Utils::CORP_NAME . " Business Unit is a required field";
        }

        if(!$data['title']){
            $errors[] = "Project Title is a required field.";
        }

        if(!$data['scope'] && !ProNav_Auth::isEmployee()){
            $errors[] = "Project Scope is a required field.";
        }

        if(!$data['stage_id']){
            $errors[] = "Project Stage is a required field."; 
        }

        return $errors;
    } 

    private function getCorpWorkgroupsLocations($corporation_id)
    {
        $o = array();
        $o['workgroups'] = $this->getWorkgroupsForCorp($corporation_id);
        $o['locations'] = $this->getLocationsForCorp($corporation_id);
        $otherlocations = Application_Model_Locations::getEndCorpLocations($corporation_id);
        $s = '';
        foreach($otherlocations as $loc){
            $name = $loc['corp_name'];
            $name .= $loc['location_name'] != $loc['corp_name'] ? ' - '.$loc['location_name'] : '';
            $s .= '<option value="'.$loc['location_id'].'">'.$name.'</option>';   
        }
        $o['other_locations'] = $s;
        return $o; 
    }

    private function getWorkgroupsForCorp($corporation_id)
    {
        $workgroups = Application_Model_Corporations::GetWorkgroups($corporation_id);
        return ProNav_Utils::getSelectOptions($workgroups,'workgroup_id','name');
    }

    private function getUsersForCorp($corporation_id)
    {
        $users = Application_Model_Corporations::GetUsers($corporation_id);
        return ProNav_Utils::getSelectOptions($users,'user_id','LFMI', ProNav_Auth::isEmployee() ? null : ProNav_Auth::getUserID());
    }

    private function getUsersForDept($workgroup_id, $format = 'FMIL')
    {
        $users = Application_Model_Users::getWorkgroupUsers($workgroup_id);
        return ProNav_Utils::getSelectOptions($users,'user_id',$format);
    }

    private function getLocationsForCorp($corporation_id)
    {
        $locations = Application_Model_Locations::getCorporationLocations($corporation_id);  
        return ProNav_Utils::getSelectOptions($locations,'location_id','name');
    }        

    private function getEligibleBillingFieldsForCorpUpdate($user_data, $projectinfo_orig, $projectinfo){
        $fields = array(
            array('acct_billing_address1', 'billing_address1', 'Billing Address Line 1'),
            array('acct_billing_address2', 'billing_address2', 'Billing Address Line 2'),
            array('acct_billing_city', 'billing_city', 'Billing City'),
            array('acct_billing_state', 'billing_state', 'Billing State'),
            array('acct_billing_zip', 'billing_zip', 'Billing Zip'),
            array('acct_billing_contact', 'billing_contact', 'A/P Contact'),
            array('acct_billing_phone', 'billing_phone', 'A/P Phone')
        );
        $corp_billing = Application_Model_Corporations::getBillingInfo($projectinfo->done_for_corporation);
        $fields_to_update = array(); //Will be a list of eligible billing fields to update on the corp.
        foreach ($fields as $f){

            $pf = $f[0];
            $cf = $f[1];
            if (isset($user_data[$pf]) && $projectinfo->$pf != $projectinfo_orig->$pf && $projectinfo->$pf != $corp_billing[$cf]){
                $fields_to_update[] = $f;
            }                                             
        }
        return $fields_to_update;
    }

}