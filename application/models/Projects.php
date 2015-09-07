 <?php
class Application_Model_Projects
{ 


    public static function GetProjectRow($project_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "SELECT p.*, ps.title AS stage_display, 
        c.po_required,
        loi.industry AS location_owner_industry_display
        FROM projects p
        INNER JOIN project_stages ps ON ps.stage_id = p.stage_id
        INNER JOIN corporations c ON c.corporation_id = p.done_for_corporation
        INNER JOIN locations l ON l.location_id = p.done_at_location
        INNER JOIN corporations lo ON lo.corporation_id = l.corporation_id
        INNER JOIN corporation_industry loi ON loi.corporation_industry_id = lo.industry
        WHERE p.project_id = ?";
        return $db->fetchRow($sql,$project_id);
    }

    /***
    * Returns information necessary to determine if a project can be closed. 
    * Returns an object with the following properties
    * ->current_stage  -- The stage the project is currently in.
    * ->current_status_percent -- The status percent the project is current at
    * ->open_co  -- The count of open Changes Order's under this project
    * 
    * @param mixed $project_id
    */
    public static function getCloseDetails($project_id)
    {
        $sql = 'SELECT stage_id AS current_stage, 
        (SELECT COUNT(*) FROM change_order_requests WHERE project_id = ? AND accounting_stage_id NOT IN (1,6,7,8)) AS invalid_cor_state
        FROM projects WHERE project_id = ?';
        $db = Zend_Db_Table::getDefaultAdapter();
        $close_details = $db->fetchRow($sql,array($project_id, $project_id));

        $project_status = Application_Model_ProjectTask::get_overall_task($project_id);                       
        $close_details->current_status_percent = $project_status->get_current_status()->project_percentage_id;
        return $close_details;
    }

    /**
    * Get a Project Object by ID
    * 
    * @param mixed $project_id
    * @return Application_Model_Project
    */
    public static function GetProjectInfo($project_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();

        $sql = "SELECT 
        sp.value as status_progress_value, sp.display_static as status_value_display,
        p.*, 
        c.name as done_for_corporation_display, c.po_required,
        dfw.name as done_for_workgroup_display, dfw.access_pct_completed,
        dbw.name as done_by_workgroup_display,
        loi.industry AS location_owner_industry_display,
        s.title as stage_title, 
        cu.firstname cu_firstname, cu.lastname cu_lastname, cu.middlename cu_middlename, 
        mu.firstname mu_firstname, mu.lastname mu_lastname, mu.middlename mu_middlename, 
        su.firstname su_firstname, su.lastname su_lastname, su.middlename su_middlename,
        acq_mu.firstname acq_mu_firstname, acq_mu.lastname acq_mu_lastname, acq_mu.middlename acq_mu_middlename,
        le.firstname acq_le_firstname, le.lastname acq_le_lastname, le.middlename acq_le_middlename,
        pm.firstname pm_firstname, pm.lastname pm_lastname, pm.middlename pm_middlename,
        pe.firstname pe_firstname, pe.lastname pe_lastname, pe.middlename pe_middlename,
        fsl.firstname fsl_firstname, fsl.lastname fsl_lastname, fsl.middlename fsl_middlename,
        bt.title bill_type_display, bt.internal bill_type_internal, 
        pp.display_static probability_display,
        sched.firstname sched_firstname, sched.lastname sched_lastname, sched.middlename sched_middlename,
        acct.firstname acct_firstname, acct.lastname acct_lastname, acct.middlename acct_middlename,
        pit.name acct_invoice_type_name, 
        pbt.building_type as building_type_display,
        poc.firstname as poc_firstname, poc.middlename as poc_middlename, poc.lastname as poc_lastname,
        epc.display acq_estimating_percent_complete_display,
        CASE WHEN pu.project_id IS NULL THEN 0 ELSE 1 END `on_project_team`,
        IFNULL(pu.favorite, 0) favorite,
        bts.state AS acct_billing_state_state, bts.state_abbr AS acct_billing_state_abbr,
        pt.display AS acct_project_type_display
        FROM projects p 
        INNER JOIN project_stages s ON p.stage_id=s.stage_id
        INNER JOIN corporations c ON c.corporation_id = p.done_for_corporation
        INNER JOIN locations l ON l.location_id = p.done_at_location
        INNER JOIN corporations lo ON lo.corporation_id = l.corporation_id
        INNER JOIN corporation_industry loi ON loi.corporation_industry_id = lo.industry
        INNER JOIN workgroups dfw ON dfw.workgroup_id = p.done_for_workgroup
        INNER JOIN workgroups dbw ON dbw.workgroup_id = p.done_by_workgroup
        LEFT JOIN users cu ON p.created_by=cu.user_id 
        LEFT JOIN users mu ON p.modified_by=mu.user_id 
        LEFT JOIN users su ON p.acq_sales_person = su.user_id
        LEFT JOIN users acq_mu ON acq_mu.user_id = p.acq_modified_by
        LEFT JOIN users sched ON p.schedule_modified_by = sched.user_id
        LEFT JOIN users acct ON p.acct_modified_by = acct.user_id
        LEFT JOIN users le ON le.user_id = p.acq_estimator
        LEFT JOIN users pm ON pm.user_id = p.acct_project_manager 
        LEFT JOIN users pe ON pe.user_id = p.acct_project_engineer 
        LEFT JOIN users fsl ON fsl.user_id = p.acct_field_staff_leader 
        LEFT JOIN project_bill_types bt ON bt.bill_type = p.bill_type
        LEFT JOIN project_percentages_probabilities pp ON pp.project_probability_id = p.acq_probability 
        LEFT JOIN project_invoice_types pit ON pit.project_invoice_type_id = p.acct_invoice_type
        LEFT JOIN project_building_types pbt ON pbt.building_type_id = p.acq_building_type
        LEFT JOIN users poc ON poc.user_id = p.point_of_contact
        LEFT JOIN project_percentages_estimating epc ON p.acq_estimating_percent_complete = epc.project_estimating_id
        LEFT JOIN project_users pu ON p.project_id = pu.project_id AND pu.user_id = ?
        LEFT JOIN states bts ON bts.state_id = p.acct_billing_state
        LEFT JOIN project_types pt ON pt.id = p.acct_project_type
        
        -- Project Tasks and the last history entry for the overall 
        LEFT JOIN project_tasks ptasks ON ptasks.project_id = p.project_id AND ptasks.is_overall = 1
        LEFT JOIN (select MAX(project_task_history_id) project_task_history_id, project_task_id FROM project_task_history GROUP BY project_task_id) ptaskhist_max ON ptasks.project_task_id = ptaskhist_max.project_task_id
        LEFT JOIN project_task_history ptaskhist ON ptaskhist_max.project_task_history_id = ptaskhist.project_task_history_id
        LEFT JOIN project_percentages_task sp ON sp.project_percentage_id = IFNULL(ptaskhist.project_percentage_id, ?)
        -- End Project Tasks
        
        WHERE p.project_id = ?";

        $object = $db->fetchRow($sql, array(ProNav_Auth::getUserID(), Application_Model_ProgressOption::STATUS_TASK_NOT_STARTED, $project_id));
        $project = new Application_Model_Project();

        if($object){
            foreach($object as $property => $value){
                $project->$property = $value;
            }
        }

        $project->DoneAtLocation = Application_Model_Locations::getLocation($object->done_at_location);
        $project->DoneForCorporation = new Application_Model_Corporation($object->done_for_corporation,$object->done_for_corporation_display);
        $project->DoneForCorporation->po_required = $object->po_required;
        $project->DoneForWorkgroup = new Application_Model_Workgroup($object->done_for_workgroup, $object->done_for_workgroup_display);
        $project->DoneByWorkgroup = new Application_Model_Workgroup($object->done_by_workgroup, $object->done_by_workgroup_display);
        
        $project->CreatedBy = new Application_Model_User($object->created_by, $object->cu_firstname, $object->cu_lastname);
        $project->PointContact = new Application_Model_User($object->point_of_contact, $object->poc_firstname, $object->poc_lastname);
        $project->Salesman = new Application_Model_User($object->acq_sales_person, $object->su_firstname, $object->su_lastname);
        $project->ModifiedBy = new Application_Model_User($object->modified_by, $object->mu_firstname, $object->mu_lastname);
        $project->AcqModifiedBy = new Application_Model_User($object->acq_modified_by, $object->acq_mu_firstname, $object->acq_mu_lastname);
        $project->ScheduledBy = new Application_Model_User($object->schedule_modified_by, $object->sched_firstname, $object->sched_lastname);
        $project->AccountingBy = new Application_Model_User($object->acct_modified_by, $object->acct_firstname, $object->acct_lastname);
        $project->Estimator = new Application_Model_User($object->acq_estimator, $object->acq_le_firstname, $object->acq_le_lastname);
        $project->ProjectManager = new Application_Model_User($object->acct_project_manager, $object->pm_firstname, $object->pm_lastname);
        $project->ProjectEngineer = new Application_Model_User($object->acct_project_engineer, $object->pe_firstname, $object->pe_lastname);
        $project->FieldStaffLeader = new Application_Model_User($object->acct_field_staff_leader, $object->fsl_firstname, $object->fsl_lastname);

        $project->BookingProbability = new Application_Model_ProgressOption($object->acq_probability,0,$object->probability_display);

        $project->BillType = new stdClass();
        $project->BillType->bill_type = $object->bill_type;
        $project->BillType->display = $object->bill_type_display;
        $project->BillType->internal = $object->bill_type_internal;

        if (property_exists($object, 'acct_invoice_state')){
            $project->InvoiceState = new stdClass();
            $project->InvoiceState->invoice_state = $object->acct_invoice_state;
            $project->InvoiceState->name = $object->acct_invoice_state_name;
        }  

        if (property_exists($object, 'acct_invoice_type')){
            $project->InvoiceType = new stdClass();
            $project->InvoiceType->invoice_type = $object->acct_invoice_type;
            $project->InvoiceType->name = $object->acct_invoice_type_name;
        }

        return $project;
    }

    public static function getStageHistory($project_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "select a.*, s.title stage_title, u.firstname, u.lastname, u.middlename from project_stage_audit a inner join project_stages s on a.stage_id=s.stage_id inner join users u on a.entered_by=u.user_id where project_id = ? order by entered_date ";
        $rows = $db->query($sql, array($project_id))->fetchAll(Zend_Db::FETCH_OBJ);
        return $rows;
    }

    /***
    * Generic method to update projects.                         
    * Array MUST contain ONLY those values to be updated.
    * Array keys MUST corrpespond directly to column names. 
    * 
    * @param array $dataArray - project data array - keys must be columns name. Must also include project_id with valid id.
    * @returns Boolean - Indicator of project table update. 
    */
    public static function updateProject($dataArray)
    {
        $project_id;

        //ensure you have a valid project id. 
        if (!array_key_exists('project_id', $dataArray) || !is_numeric($dataArray['project_id'])) {
            //no id - can't update every project, so return.
            return 0;                 
        } else {
            $project_id = $dataArray['project_id'];
            unset($dataArray['project_id']); //don't want that to be updated. 
        }

        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->update('projects', $dataArray,$db->quoteInto('project_id = ?',$project_id));      
        
              
    }

    public static function GetProjectCountsByStage($filter)
    {

        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "SELECT s.stage_id, s.title, ifnull(t.cnt,0) cnt, t.min_id from project_stages s 
        LEFT JOIN
        (SELECT p.stage_id, s.title, count(*) cnt, min(p.project_id) AS min_id 
        FROM projects p 
        INNER JOIN project_stages s ON p.stage_id=s.stage_id 
        INNER JOIN locations l ON p.done_at_location=l.location_id "
        . Application_Model_Projects::getSQLFromFilter($filter) .
        " GROUP BY p.stage_id, s.title) t ON s.stage_id=t.stage_id 
        ORDER BY s.sort";

        return $db->query($sql)->fetchAll();            
    }

    public static function GetMyProjects($includeValue = false, $order_column = 'project_id', $order_direction = 1)
    {
        $current_user = ProNav_Auth::getUserID();

        $filter = array();
        $filter['my_watch_list'] = 1;
        $projects = Application_Model_Projects::GetAllProjects($filter, true, $order_column, $order_direction);

        if ($includeValue && !empty($projects)){
            $keys =  implode(",", array_keys($projects));
            $db = Zend_Db_Table::getDefaultAdapter();
            $sql = "SELECT p.project_id, (p.acct_project_value + IFNULL(c.cor_total,0)) AS totalValue FROM projects p 
            LEFT JOIN (
            SELECT project_id, SUM(IFNULL(amount,0)) AS cor_total 
            FROM change_order_requests 
            WHERE accounting_stage_id = 1 
            GROUP BY project_id
            ) c ON p.project_id = c.project_id
            WHERE p.project_id IN ($keys)";


            $data = $db->fetchAll($sql);
            if ($data){
                foreach ($data as $row){
                    if ($projects[$row->project_id]){
                        $projects[$row->project_id]->totalValue = $row->totalValue;
                    }
                }
            }
        }

        return $projects;
    }

    public static function GetAllProjects($filter, $forMyProject = false, $order_column = 'project_id', $order_direction = 1)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $projects = array();
        $sql = 'SELECT 
        p.*, 
        c.name AS corporation_name, 
        w.name AS workgroup_name, w.access_pct_completed, 
        l.name AS location_name, 
        s.title AS stage_title, 
        b.name AS trim_business_unit, 
        lc.name AS lc_name, 
        sp.project_percentage_id, sp.value AS status_progress_value, sp.display_static AS status_value_display, 
        up.firstname pcon_firstname, up.lastname pcon_lastname,
        CASE WHEN pf.project_id IS NULL THEN 0 ELSE 1 END `on_project_team`,
        IFNULL(pf.favorite, 0) favorite
        FROM projects p 
        INNER JOIN corporations c ON p.done_for_corporation=c.corporation_id 
        INNER JOIN locations l ON p.done_at_location = l.location_id 
        LEFT JOIN workgroups w ON p.done_for_workgroup=w.workgroup_id 
        INNER JOIN project_stages s ON p.stage_id=s.stage_id 
        INNER JOIN workgroups b ON p.done_by_workgroup=b.workgroup_id 
        INNER JOIN corporations lc ON l.corporation_id=lc.corporation_id 
        LEFT JOIN users up ON p.point_of_contact=up.user_id 
        LEFT JOIN project_users pf ON p.project_id = pf.project_id AND pf.user_id = ?
        -- Project Tasks and the last history entry for the overall 
        LEFT JOIN project_tasks ptasks ON ptasks.project_id = p.project_id AND ptasks.is_overall = 1
        LEFT JOIN (select MAX(project_task_history_id) project_task_history_id, project_task_id FROM project_task_history GROUP BY project_task_id) ptaskhist_max ON ptasks.project_task_id = ptaskhist_max.project_task_id
        LEFT JOIN project_task_history ptaskhist ON ptaskhist_max.project_task_history_id = ptaskhist.project_task_history_id
        LEFT JOIN project_percentages_task sp ON sp.project_percentage_id = IFNULL(ptaskhist.project_percentage_id, 14)
        -- End Project Tasks
        %s';


        if ($order_direction == 0){
            $order_direction = ' desc';
        } else {
            $order_direction = ' asc';
        }


        $order_str = '';
        switch ($order_column){
            case 'project': 
                $order_str .= ' ORDER BY p.stage_id ASC, p.project_id ' . $order_direction; 
                break;
            case 'job_no':
                $order_str .= ' ORDER BY p.stage_id ASC, p.job_no '. $order_direction;
                break;
            case 'cust_ref_no':
                $order_str .= ' ORDER BY p.stage_id ASC, p.ref_no '. $order_direction;
                break;
            case 'location':
                $order_str .= " ORDER BY p.stage_id ASC, lc.name $order_direction, l.name $order_direction, p.title $order_direction";
                break;
            default: 
                $order_str .= ' ORDER BY p.stage_id ASC, p.project_id ASC';
                break;
        }

        $sql .= $order_str;

        $sql = sprintf($sql, Application_Model_Projects::getSQLFromFilter($filter, $forMyProject));

        $rows = $db->query($sql, ProNav_Auth::getUserID())->fetchAll();

        foreach($rows as $row){
            $project = Application_Model_Projects::constructProjectFromData($row);
            $projects[$project->project_id] = $project;
        }

        return $projects;
    }


    private static function constructProjectFromData($data)
    {
        $project = new Application_Model_Project();
        if ($data)
        {
            foreach($data as $property => $value){
                $project->$property = $value;         
            }               

            if ($project->pcon_firstname){
                $project->PointContact = new Application_Model_User();
                $project->PointContact->firstname = $project->pcon_firstname;
                $project->PointContact->lastname = $project->pcon_lastname;
                $project->PointContact->user_id = $project->point_of_contact;        
            }                                                                    
        }                                                                
        return $project;
    }

    private static function getSQLFromFilter($filter, $forMyProject=false)
    {
        $db = Zend_Db_Table::getDefaultAdapter();

        $sql = '';

        if($filter['my_watch_list'] != 0) {
            $sql .= $db->quoteInto(" INNER JOIN project_users pu ON p.project_id=pu.project_id AND pu.user_id = ?", ProNav_Auth::getUserID());
        }

        $sql .= ' WHERE '. ProNav_Auth::getProjectRestriction();

        if($filter['done_for_corporation'] != 0) {
            $sql .= $db->quoteInto(" AND p.done_for_corporation = ? ", $filter['done_for_corporation']);
        }

        if($filter['done_for_workgroup'] != 0) {
            $sql .= $db->quoteInto(" AND p.done_for_workgroup = ? ", $filter['done_for_workgroup']);
        }

        if($filter['done_at_location'] != 0) {
            $sql .= $db->quoteInto(" AND p.done_at_location = ? ", $filter['done_at_location']);
        }

        if($filter['location_owner'] != 0) {
            $sql .= $db->quoteInto(" AND l.corporation_id = ?", $filter['location_owner']);
        }

        if($filter['done_by_workgroup'] != 0) {
            $sql .= $db->quoteInto(" AND p.done_by_workgroup = ? ", $filter['done_by_workgroup']);
        }

        if($filter['point_of_contact'] != 0) {
            $sql .= $db->quoteInto(" AND p.point_of_contact = ? ", $filter['point_of_contact']);
        }

        if($filter['stage_id'] != 0) {
            $sql .= $db->quoteInto(" AND p.stage_id = ?", $filter['stage_id']);     
        }

        if($filter['keywords'] != "") {
            $term = '%'. $filter['keywords'] .'%'; 
            $sql .= $db->quoteInto(" AND (p.title like ? ", $term); 
            $sql .= $db->quoteInto(" OR p.po_number LIKE ? ", $term);
            $sql .= $db->quoteInto(" OR (p.project_id = ?)", $filter['keywords']); /* need exact match for this */
            $sql .= $db->quoteInto(" OR p.job_no LIKE ? ", $term);
            $sql .= $db->quoteInto(" OR p.ref_no LIKE ? ", $term);
            $sql .= $db->quoteInto(" OR c.name LIKE ?", $term);    
            $sql .= $db->quoteInto(" OR l.name LIKE ?", $term);    
            $sql .= $db->quoteInto(" OR lc.name LIKE ?)", $term);       
        }

        if($forMyProject) {
            $sql .= sprintf(" AND p.stage_id != %d AND p.stage_id != %d", 
                ProNav_Utils::STAGE_CANCELLED, ProNav_Utils::STAGE_CLOSED);
        }

        return $sql;                                            
    }

    public static function QuickSearch($keyword)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $term = '%'.$keyword.'%';
        $sql = "SELECT project_id 
        FROM projects p 
        INNER JOIN corporations o ON o.corporation_id = p.done_for_corporation
        INNER JOIN locations l ON l.location_id = p.done_at_location
        INNER JOIN corporations lo ON lo.corporation_id = l.corporation_id
        WHERE ";
        $sql .= ProNav_Auth::getProjectRestriction();
        $sql .= $db->quoteInto(" AND (title like ? ", $term); 
        $sql .= $db->quoteInto(" or po_number like ? ", $term);
        $sql .= $db->quoteInto(" or (project_id = ?) ", $keyword);
        $sql .= $db->quoteInto(" or job_no like ? ", $term);
        $sql .= $db->quoteInto(" or ref_no like ?", $term);
        $sql .= $db->quoteInto(" or o.name like ?", $term);
        $sql .= $db->quoteInto(" or l.name like ?", $term);
        $sql .= $db->quoteInto(" or lo.name like ?)", $term);

        $sql .= ' ORDER BY project_id ASC';

        $oDr = $db->query($sql)->fetchAll();

        $o = array();
        $o['cnt'] = count($oDr);
        if($o['cnt'] == 1){
            $o['project_id'] = $oDr[0]->project_id;
        }

        return $o; 
    }

    public static function SaveFilter($filter)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $user = array('project_filter' => Zend_Json::encode($filter));
        return $db->update('users', $user, $db->quoteInto('user_id = ?', ProNav_Auth::getUserID()));            
    }

    public static function GetDefaultFilter()
    {
        $user_id = ProNav_Auth::getUserID();
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchRow("select project_filter from users where user_id=?",$user_id);            
    }

    public static function CreateProject($data){
        $db = Zend_Db_Table::getDefaultAdapter();
        $current_user = ProNav_Auth::getUserID();  

        //get corporation level detail to copy over. 
        $corp = Application_Model_Corporations::GetCorporationRow($data['done_for_corporation']);
        $data['acct_tax_exempt'] = $corp->tax_exempt;

        $project = array(
            'done_for_corporation' => $data['done_for_corporation'],
            'done_for_workgroup' => $data['done_for_workgroup'],
            'done_at_location' => $data['done_at_location'],
            'title' => $data['title'],      
            'scope' => $data['scope'], 
            'done_by_workgroup' => $data['done_by_workgroup'],
            'stage_id' => $data['stage_id'],
            'bill_type' => ($data['bill_type'] ? $data['bill_type'] : new Zend_Db_Expr("NULL")), 
            'status' => 0,
            'po_number' => ($data['po_number'] ? $data['po_number'] : new Zend_Db_Expr("NULL")),
            'po_date' => ProNav_Utils::toMySQLDate($data['po_date']),
            'po_amount' => ($data['po_amount'] ? $data['po_amount'] : new Zend_Db_Expr("NULL")),
            'ref_no' => ($data['ref_no'] ? $data['ref_no'] : new Zend_Db_Expr("NULL")),
            'schedule_estimated_start' => ProNav_Utils::toMySQLDate($data['schedule_estimated_start']), 
            'schedule_estimated_end' => ProNav_Utils::toMySQLDate($data['schedule_estimated_end']),
            'schedule_modified_by' => ($data['schedule_not_before'] || $data['schedule_required_by'] ? $current_user : new Zend_Db_Expr("NULL")),
            'schedule_modified' => ($data['schedule_not_before'] || $data['schedule_required_by'] ? new Zend_Db_Expr("NOW()") : new Zend_Db_Expr("NULL")),
            'work_type' => ($data['work_type'] ? $data['work_type'] : new Zend_Db_Expr("NULL")),
            'point_of_contact' => ($data['point_of_contact'] ? $data['point_of_contact'] : new Zend_Db_Expr("NULL")),
            'acct_tax_exempt' =>  $data['acct_tax_exempt'],
            'requested_by' => $data['requested_by'] ? $data['requested_by'] : new Zend_Db_Expr("NULL"),                            
            'acq_sales_person' => $data['acq_sales_person'] ? $data['acq_sales_person'] : new Zend_Db_Expr('NULL'),
            'acq_probability' => $data['acq_probability'] == -1 ? new Zend_Db_Expr('NULL') : $data['acq_probability'],
            'acq_booking_month' => $data['acq_booking_month'] == -1 ? new Zend_Db_Expr('NULL') : $data['acq_booking_month'],
            'acq_booking_year' => $data['acq_booking_year'] == -1 ? new Zend_Db_Expr('NULL') : $data['acq_booking_year'],
            'acq_estimator' => $data['acq_estimator'] ? $data['acq_estimator'] : new Zend_Db_Expr('NULL'),
            'acq_pre_bid_mandatory' => ($data['acq_pre_bid_mandatory'] == '1' ? '1' : ($data['acq_pre_bid_mandatory'] == '0' ? 0 : new Zend_Db_Expr('NULL'))),
            'acq_pre_bid_date' => ProNav_Utils::toMySQLDate($data['acq_pre_bid_date']),
            'acq_bid_date' => ProNav_Utils::toMySQLDate($data['acq_bid_date']),
            'acq_bid_review_meeting' => ProNav_Utils::toMySQLDate($data['acq_bid_review_meeting']),
            'acq_estimating_hours' => (is_numeric($data['acq_estimating_hours']) ? $data['acq_estimating_hours'] : new Zend_Db_Expr('NULL')),
            'acct_billing_address1' => $corp->billing_address1,
            'acct_billing_address2' => $corp->billing_address2,
            'acct_billing_city' => $corp->billing_city,
            'acct_billing_state' => $corp->billing_state,
            'acct_billing_state' => $corp->billing_state,
            'acct_billing_zip' => $corp->billing_zip,
            'acct_billing_phone' => $corp->billing_phone,
            'acct_billing_contact' => $corp->billing_contact,
            'acct_billing_notes' => $corp->billing_notes,
            'created_by' => $current_user,
            'created_date' => new Zend_Db_Expr("NOW()")
        );

        $db->insert('projects', $project);
        $new_project_id = $db->lastInsertId();            

        if($data['stage_id'] == ProNav_Utils::STAGE_OPEN){
            $overall_status = Application_Model_ProjectTask::get_overall_task($new_project_id);
            $overall_status->add_new_status(Application_Model_ProgressOption::STATUS_TASK_STARTED);
        }
        
        //insert into the stage audit table
        $stage = array('project_id' => $new_project_id, 'stage_id' => $data['stage_id'], 'comment' => $data['comment'], 'entered_by' => $current_user, 'entered_date' => new Zend_Db_Expr("NOW()"));
        $db->insert('project_stage_audit', $stage);           

        //If user is not an employee we need to append employees to the project watch list.
        //Per their subscriptions -- This cannot be modified by the client at project creation.
        if (!ProNav_Auth::isEmployee()){
            $queue = new stdClass();
            $queue->project_id = -1;
            $queue->event_type = ProNav_Notification::EVENT_PROJECT_CREATED; 
            $queue->only_action = ProNav_NotificationProject::ACTION_ADD_TO_TEAM;
            $queue->done_for_corporation = $data['done_for_corporation'];
            $queue->done_for_workgroup = $data['done_for_workgroup'];
            $queue->done_by_workgroup = $data['done_by_workgroup'];
            $queue->done_at_location = $data['done_at_location'];
            $queue->location_owner = $data['location_owner'];
            $oNotifyQ = new ProNav_NotificationProject($queue);
            $subscribers = $oNotifyQ->getSubscribers();

            foreach ($subscribers as $user){
                if ($user->corporation_id == ProNav_Utils::TriMId){
                    $data['team'][] = $user->user_id;
                }   
            }           
        }

        //insert to the project team table
        foreach ($data['team'] as $user_id){
            $db->query("CALL add_user_to_team(?, ?, ?)", array($user_id, $new_project_id, $current_user))->execute();  
        }


        ProNav_Utils::registerEvent($new_project_id, ProNav_Notification::EVENT_PROJECT_CREATED, 
            $data['stage_id'], $current_user, new Zend_Db_Expr('Now()')); 

        return $new_project_id;   
    } 

    public static function getPrevStageInfo($project_id){
        $db = Zend_Db_Table::getDefaultAdapter(); 
        $orig = $db->query("select stage_id, getPriorStage(project_id) as prev_stage_id from projects where project_id = ?", array($project_id))->fetchObject();   
        return $orig;
    }

    public static function UpdateStage($project_id, $data){

        $db = Zend_Db_Table::getDefaultAdapter();
        $current_user = ProNav_Auth::getUserID(); 

        $orig = self::getPrevStageInfo($project_id); 
        $from_stage = $orig->stage_id;
        $stage_before_from_stage = $orig->prev_stage_id ? $orig->prev_stage_id : ProNav_Utils::STAGE_OPEN;  

        $update_data = array();

        $update_data['stage_id'] = $data['to_stage'] == -1 ? $stage_before_from_stage : $data['to_stage']; //-1 is release from hold

        //All the fields that could be in the data array. 
        //Only operates on them if the key exists in the $data array. 
        //As not all fields are always present and we do not want to clear existing fields that are not present. 
        //key, type, default value. 
        $possible_flds = array(
            array('done_for_corporation', 1),
            array('done_for_workgroup', 1),
            array('point_of_contact', 1),
            array('po_number', 0),
            array('po_amount', 2),
            array('po_date', 3),
            array('bill_type', 1),
            array('schedule_estimated_start', 3),
            array('schedule_estimated_end', 3),
            array('acct_estimated_cost', 2),
            array('acct_retainage', 0),
            array('acct_prevailing_wage', 4),
            array('acct_tax_exempt', 4),
            array('acct_billing_address1', 0),
            array('acct_billing_address2', 0),
            array('acct_billing_city', 0),
            array('acct_billing_state', 1),
            array('acct_billing_zip', 0),
            array('acct_billing_notes', 0),
            array('acct_billing_contact', 0),
            array('acct_billing_phone', 0),
            array('acct_invoice_type', 1),
            array('acct_billing_date', 1),
            array('acct_cert_of_ins_req', 4),            
            array('acct_ocip', 4),            
            array('acct_performance_bond_req', 4),
            array('acct_permit_req', 4),
            array('acct_project_type', 1),
            array('acct_project_value', 2),
            array('acct_project_manager', 1),
            array('job_no', 0)        
        );

        foreach ($possible_flds as $fld){

            $key = $fld[0];
            $type = $fld[1];
            $default_val = count($fld)== 3 ? $fld[2] : new Zend_Db_Expr('NULL');

            if (array_key_exists($key, $data)){                 

                $val = $data[$key];

                switch ($type){
                    case 1:  //Numbers
                        if (is_numeric($val) && $val > 0){
                            $update_data[$key] = $val;
                        } else {
                            $update_data[$key] = $default_val;
                        }
                        break;
                    case 2: //Formatted Numbers
                        $val = ProNav_Utils::stripFormattedNumbers($val);
                        if (is_numeric($val)){
                            $update_data[$key] = $val;
                        } else {
                            $update_data[$key] = $default_val;                            
                        }
                        break;
                    case 3: //Dates
                        if ($val){
                            $update_data[$key] = ProNav_Utils::toMySQLDate($val, true) ;
                        } else {
                            $update_data[$key] = $default_val;
                        }
                        break;
                    case 4:  //Boolean
                        $update_data[$key] = $val == '1' ? 1 : 0;
                        break;
                    default: //Text
                        $val = trim($val);
                        if ($val){
                            $update_data[$key] = $val;
                        } else {
                            $update_data[$key] = $default_val;
                        }                                
                        break;
                }
            }
        }

        $comment = $data['comment'];

        $db->update('projects', $update_data, $db->quoteInto('project_id = ?', $project_id));

        //if this is the first time you are going into the open stage, then set the status percent to 'started'.
        if ($data['stage_id'] == ProNav_Utils::STAGE_OPEN && !Application_Model_Projects::hasBeenInStage($project_id, ProNav_Utils::STAGE_OPEN)){                                                                                                           
            $overall = Application_Model_ProjectTask::get_overall_task($project_id);        
            $overall->add_new_status(Application_Model_ProgressOption::STATUS_TASK_STARTED, 'Project Opened');            
        }             
        
        $resubmit = $data['resubmit'] ? 1 : 0;

        //stage audit
        if(($orig->stage_id != $update_data['stage_id']) || $resubmit == 1){
            $stage = array('project_id' => $project_id, 'stage_id' => $update_data['stage_id'], 'comment' => $comment , 
                'entered_by' => $current_user, 'entered_date' => new Zend_Db_Expr("NOW()"));
            $db->insert('project_stage_audit', $stage);

            if($orig->stage_id == ProNav_Utils::STAGE_POTENTIAL){
                if($update_data['stage_id'] != ProNav_Utils::STAGE_CANCELLED) {
                    ProNav_Utils::registerEvent($project_id, 1, $update_data['stage_id'], 
                        $current_user, new Zend_Db_Expr('Now()')); 
                }
            } else { 
                ProNav_Utils::registerEvent($project_id, 2, $update_data['stage_id'], 
                    $current_user, new Zend_Db_Expr('Now()'));
            }
        }
    }

    public static function isPORequired($project_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "SELECT c.po_required FROM corporations c 
        INNER JOIN projects p ON p.done_for_corporation = c.corporation_id 
        WHERE project_id = ?";
        return $db->fetchOne($sql,$project_id);    
    }

    public static function UpdateProjectInfo($project_id, $data)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $current_user = ProNav_Auth::getUserID();

        $orig = $db->query("SELECT * FROM projects WHERE project_id = ?", array($project_id))->fetchObject(); 

        $project = array(
            'title' => $data['title'],
            'stage_id' => $data['stage_id'],
            'point_of_contact' => $data['point_of_contact'],
            'schedule_not_before' => $data['schedule_not_before'],
            'schedule_required_by' => $data['schedule_required_by'],
            'ref_no' => $data['ref_no'],
            'requested_by' => $data['requested_by'] ? $data['requested_by'] : new Zend_Db_Expr("NULL"),
            'modified_by' => $current_user,
            'modified_date' => new Zend_Db_Expr("NOW()")
        );

        if(ProNav_Auth::hasPerm(ProNav_Auth::PERM_PROJECTS_OVERVIEW_EDIT) && isset($data['done_for_corporation']))
        {
            $project['done_for_corporation'] = $data['done_for_corporation'];
            $project['done_for_workgroup'] = $data['done_for_workgroup'];
            $project['done_at_location'] = $data['done_at_location'];
            $project['done_by_workgroup'] = $data['done_by_workgroup'];
        }      

        if ($orig->done_for_corporation != $data['done_for_corporation']){
            //Done for corporaton changed. Update Billing Info.
            $billing_info = Application_Model_Corporations::getBillingInfo($data['done_for_corporation']);
            $project['acct_billing_address1'] = $billing_info['billing_address1'];
            $project['acct_billing_address2'] = $billing_info['billing_address2'];
            $project['acct_billing_city'] = $billing_info['billing_city'];
            $project['acct_billing_state'] = $billing_info['billing_state'];
            $project['acct_billing_zip'] = $billing_info['billing_zip'];
            $project['acct_billing_phone'] = $billing_info['billing_phone'];
            $project['acct_billing_contact'] = $billing_info['billing_contact'];
            $project['acct_billing_notes'] = $billing_info['billing_notes'];
        }

        $db->update('projects', $project, $db->quoteInto('project_id = ?', $project_id));            

        //if this is the first time you are going into the open stage, then set the status percent to 'started' per the requirements. 
        if ($data['stage_id'] == ProNav_Utils::STAGE_OPEN && 
            !Application_Model_Projects::hasBeenInStage($project_id, ProNav_Utils::STAGE_OPEN)){                                                                                                           
            
                $overall = Application_Model_ProjectTask::get_overall_task($project_id);
                $overall->add_new_status(Application_Model_ProgressOption::STATUS_TASK_STARTED);
        }
        
        //stage audit
        if($orig->stage_id != $data['stage_id']){
            $stage = array('project_id' => $project_id, 'stage_id' => $data['stage_id'], 'comment' => $data['stage_comment'] , 'entered_by' => $current_user, 'entered_date' => new Zend_Db_Expr("NOW()"));
            $db->insert('project_stage_audit', $stage);

            if($orig->stage_id == ProNav_Utils::STAGE_POTENTIAL){
                if($data['stage_id'] != ProNav_Utils::STAGE_CANCELLED){
                    ProNav_Utils::registerEvent($project_id, 1, $data['stage_id'], $current_user, new Zend_Db_Expr('Now()')); 
                }
            } else {
                ProNav_Utils::registerEvent($project_id, 2, $data['stage_id'], $current_user, new Zend_Db_Expr('Now()'));
            }
        }     
    }     

    public static function hasBeenInStage($project_id, $stage_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchOne('SELECT COUNT(*) FROM project_stage_audit WHERE project_id = ? AND stage_id = ?',
            array($project_id,$stage_id));
    }

    public static function GetStages($isEmployee = false)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        if ($isEmployee)
        {
            return $db->fetchAll("SELECT stage_id, title FROM project_stages ORDER BY sort");               
        }
        else 
        {
            //ADA - Fixed on 1/24/13. client_visible and client_selectable did not exist in the db. 
            //I added only client_visible and refactored. This seems to be the only place this is referenced. 
            return $db->fetchAll("SELECT stage_id, title FROM project_stages 
            WHERE client_visible = 1 ORDER BY sort;");
        }
    }

    public static function GetBillTypes($includeInactive = false, $includeNonClientAction = true)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "select * from project_bill_types ";
        $where = '';
        if (!$includeInactive)
        {
            $where = ' active = 1 ';
        }

        if (!$includeNonClientAction)
        {
            if ($where != ''){$where .= ' AND ';}
            $where .= ' client_action = 1';
        }

        $sql .= ($where == '' ? '' : (' WHERE ' . $where));
        $sql .= " ORDER BY display_order"; 
        return $db->fetchAll($sql);
    }

    public static function billTypeIsInternal($bill_type_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $bill_type_id = Zend_Filter_Digits::filter($bill_type_id);
        return $db->fetchOne('SELECT inactive FROM project_bill_types WHERE bill_type = ?',$bill_type_id);
    }

    public static function getInvoiceStates()
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchAll("SELECT project_invoice_state_id, name, active FROM project_invoice_states ORDER BY display_order");
    }

    public static function getInvoiceTypes()
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchAll("SELECT project_invoice_type_id, name,active FROM project_invoice_types ORDER BY display_order");
    }

    public static function getProjectPercentages()
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchAll("SELECT * FROM project_percentages_probabilities ORDER BY display_order;");
    }

    public static function getBuildingTypes()
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $data = $db->fetchAll("SELECT * FROM project_building_types");
        $buildings = array();
        if ($data){
            foreach ($data as $row){
                $buildings[$row->building_type_id] = $row;
            }
        }
        return $buildings;
    }

    public static function GetWorkTypes()
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchAll("select work_type, title from project_work_types order by title");
    }

    public static function GetScopes($project_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $stmt = $db->query("select scope, scope_internal from projects where project_id=?", array($project_id));
        return $stmt->fetch(Zend_Db::FETCH_OBJ);
    }

    public static function SaveScope($project_id, $data)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $current_user = ProNav_Auth::getUserID();  

        $scopes = array('scope' => $data['scope'],
            'scope_internal' => $data['scope_internal'],
            'modified_by' => $current_user,
            'modified_date' => new Zend_Db_Expr("NOW()")
        ); 

        if(trim($data['scope']) == "" || !$data['scope']){
            $scopes['scope'] = new Zend_Db_Expr("NULL");
        }

        if(!$data['scope_internal']){
            $scopes['scope_internal'] = new Zend_Db_Expr('NULL');
        }

        $n = $db->update('projects', $scopes, $db->quoteInto('project_id = ?', $project_id));

        return $n;  
    } 
	
	
	public static function GetMaterialLocation($project_id)
    {
	
        $db = Zend_Db_Table::getDefaultAdapter();
       $stmt = $db->query("select material_location from projects where project_id=?", array($project_id));
	     return $stmt->fetch(Zend_Db::FETCH_OBJ);
    }

    public static function SaveMaterialLocation($project_id, $data)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $current_user = ProNav_Auth::getUserID();  

        $material = array('material_location' => $data['material_location'],
            'modified_by' => $current_user,
            'modified_date' => new Zend_Db_Expr("NOW()")
        ); 

        if(trim($data['material_location']) == "" || !$data['material_location']){
            $material['material_location'] = new Zend_Db_Expr("NULL");
        }

        $n = $db->update('projects', $material, $db->quoteInto('project_id = ?', $project_id));

        return $n;  
    } 

	

    public static function getProjectTeam($project_id,$includeInactive = 0)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "SELECT u.user_id, u.firstname, u.lastname, u.title, u.email, u.date_login, u.phone_office,
        u.phone_mobile, u.pronav_access,
        c.corporation_id, c.name AS corporation_name
        FROM users u 
        INNER JOIN corporations c on c.corporation_id = u.corporation_id
        INNER JOIN project_users pu ON pu.user_id = u.user_id
        WHERE pu.project_id = ?";

        if (!$indludeInactive){
            $sql .= " AND u.status = 0";
        }

        $sql .= ' ORDER BY u.lastname, u.firstname';

        $data = $db->fetchAll($sql,array($project_id));

        $users = array();
        if (!$data){return $users;}           

        foreach ($data as $row)
        {
            $user = new Application_Model_User();
            foreach ($row as $key => $value)
            {
                $user->$key = $value;   
            }

            $corp = new Application_Model_Corporation();
            $corp->corporation_id = $user->corporation_id;
            $corp->name = $user->corporation_name;
            $user->Corporation = $corp;

            $users[$user->user_id] = $user;
        }
        return $users;           
    }            

    public static function getProjectTeamAvailable($projectId, $corpId, $departmentId = -1)
    {

        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = null;
        $data = null;
        if (ProNav_Auth::isEmployee()){               
            /* Get all corporation user */
            $sql = "SELECT u.user_id, u.firstname, u.lastname, u.pronav_access, c.name AS corporation_name, c.corporation_id, a.project_id
            FROM users u
            INNER JOIN corporations c ON c.corporation_id = u.corporation_id
            LEFT JOIN
            (
            SELECT * FROM project_users WHERE project_id = ?
            ) a ON a.user_id = u.user_id
            WHERE c.corporation_id = ? AND a.project_id is null AND u.status = 0
            ORDER BY u.lastname, u.firstname
            ";                    
            $data = $db->fetchAll($sql,array($projectId, $corpId));
        }
        elseif ($projectId != -1)
        {
            //only get users from the current users corporation 
            //they are not an employee and can only add/remove people from their corporation.
            $sql = "SELECT u.user_id, u.firstname, u.lastname, u.pronav_access, c.name AS corporation_name, c.corporation_id 
            FROM users u
            INNER JOIN corporations c ON c.corporation_id = u.corporation_id
            WHERE u.status = 0 AND u.user_id IN (
            SELECT wu.user_id FROM workgroup_users wu
            INNER JOIN projects p ON p.done_for_workgroup = wu.workgroup_id
            LEFT JOIN (select * from project_users where project_id = ?) pu ON pu.user_id = wu.user_id
            WHERE p.project_id = ? AND pu.user_id is null);";
            $data = $db->fetchAll($sql, array($projectId, $projectId));                 
        }
        else
        {
            //This branch is necessary for when you don't yet have a project id. 
            //You need to join on department and get users from the given deperatment.
            $sql = "SELECT u.user_id, u.firstname, u.lastname, u.pronav_access, c.name AS corporation_name, c.corporation_id
            FROM users u
            INNER JOIN corporations c ON c.corporation_id = u.corporation_id
            WHERE u.status = 0 AND u.user_id IN (SELECT w.user_id FROM workgroup_users w WHERE w.workgroup_id = ?)";
            $data = $db->fetchAll($sql,$departmentId);
        }

        $users = array();
        if (!$data){return $users;}

        foreach ($data as $row)
        {
            $user = new Application_Model_User();
            foreach ($row as $key => $value)
            {
                $user->$key = $value;
            }
            $corp = new Application_Model_Corporation();
            $corp->name = $user->corporation_name;
            $corp->corporation_id = $user->corporation_id;
            $user->Corporation = $corp;
            $users[] = $user;
        }
        return $users;            
    }   

    public static function getProjectTeamAssigned($projectId)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = null;
        $data = null;

        if (ProNav_Auth::isEmployee())
        {
            $sql = "select u.user_id, u.firstname, u.lastname, c.name AS corporation_name, c.corporation_id
            FROM users u
            INNER JOIN corporations c ON c.corporation_id = u.corporation_id
            INNER JOIN project_users pu ON pu.user_id = u.user_id
            WHERE pu.project_id = ?;";
            $data = $db->fetchAll($sql, $projectId);    
        }
        else
        {
            /* Only get users from the projects done_for_workgroup (department) */
            $sql = "SELECT u.user_id, u.firstname, u.lastname, c.name AS corporation_name, c.corporation_id 
            FROM users u
            INNER JOIN corporations c ON u.corporation_id = c.corporation_id 
            WHERE user_id IN (
            SELECT wu.user_id FROM workgroup_users wu
            INNER JOIN projects p ON p.done_for_workgroup = wu.workgroup_id
            INNER JOIN (select * from project_users where project_id = ?) pu ON pu.user_id = wu.user_id
            WHERE p.project_id = ?)";
            $data = $db->fetchAll($sql,array($projectId, $projectId));
        }

        $users = array();
        if (!$data){return $users;}
        foreach ($data as $row)
        {
            $user = new Application_Model_User();
            foreach ($row as $key => $value)
            {
                $user->$key = $value;
            }
            $corp = new Application_Model_Corporation();
            $corp->name = $user->corporation_name;
            $corp->corporation_id = $user->corporation_id;
            $user->Corporation = $corp;
            $users[] = $user;
        }
        return $users;
    }

    /**
    * Expects a project id and an associative array of user_id => assignment option.
    * If the assignment option is 1 the user will be added. 
    * If the assignment option is 0 the user will be removed. 
    * 
    * @param int $projectId - The Project Id
    * @param Array $userList - Associative Array of user_id => assignment option.
    * @returns void
    * @author Andrew
    * @todo Create a MySQL stored proc to accept the array so you only have to make 1 database call.
    */
    public static function setProjectTeamAssignments($projectId, $userList)
    {
        if (is_array($userList))
        {
            $db = Zend_Db_Table::getDefaultAdapter();
            foreach ($userList as $key => $value)
            {
                if ($value == 1)
                {
                    $db->query('INSERT INTO project_users (project_id, user_id) VALUES (?,?) 
                        ON DUPLICATE KEY UPDATE user_id = user_id',array($projectId,$key));
                } 
                else if ($value == 0)
                {
                    $db->query('DELETE FROM project_users WHERE project_id = ? AND user_id = ?',array($projectId,$key));
                }
            }
        }
    }

    /***
    * This method is used when clients assign users to the project team. 
    * I need to compare their requested user id's against those user id's in the
    * department for which this job is being done. 
    * I don't allow them to perform any action on any user not in the project's done_for_workgroup (department)
    * 
    * @param mixed $project_id
    */
    public static function getProjectDepartmentUsers($project_id)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        $sql = "SELECT wu.user_id FROM workgroup_users wu
        INNER JOIN projects p ON p.done_for_workgroup = wu.workgroup_id
        WHERE p.project_id = ?";
        $data = $db->fetchAll($sql,$project_id);
        if (!$data)
        {
            return array();
        }
        else 
        {
            $user_ids = array();
            foreach ($data as $row)
            {
                $user_ids[] = $row->user_id;
            }                
            return $user_ids;
        }            
    }

    /**
    * Returns the bill to corporatoin id for a given project id. 
    * This is used primiarly in determining the appropriate default corporation to select 
    * when displaying the project teams assignment dialog. 
    * 
    * @param int $projectId - The Project Id. 
    * @return int - The bill to corporation id for the given project id. 
    * @author Andrew
    */
    public static function getProjectBillToCorporationId($projectId)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchOne("SELECT done_for_corporation FROM projects WHERE project_id = ?", array($projectId));
    }

    /**
    * Returns the project stage id and the internal value for a given project id.
    * 
    * @param int $projectId
    * @return stdClass - or null if project is not found
    *   ->stage_id Int
    *   ->internal Int
    * 
    */
    public static function getProjectStage($projectId)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchOne('SELECT stage_id FROM projects WHERE project_id = ?', $projectId);          
    }

    public static function getProjectBusinessUnit($projectId)
    {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchOne('SELECT done_by_workgroup FROM projects WHERE project_id = ?', $projectId);
    }

    public static function getYearList($existingValue = null, $add = 3){
        $date = new Zend_Date();
        $current = $date->toString('y');
        $end_at = ($current + $add);

        if ($end_at < $current || ($end_at-$current) < 2) {
            $end_at = $current + 2;
        }

        $years = array();
        for ($i = $current; $i < $end_at; $i++){
            $years[] = $i;
        }

        if ($existingValue){
            $years[] = $existingValue;
            $years = array_unique($years);
            sort($years);
        }
        return $years;            
    }  

    public static function OpenProjectsCountAtLocation($done_at_location, $project_id)
    {           
        return count(Application_Model_Projects::OpenProjectsAtLocation($done_at_location, $project_id));
    }  

    public static function OpenProjectsAtLocation($done_at_location, $project_id) {
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchAll("select p.project_id, p.title, w.name from projects p inner join workgroups w on p.done_by_workgroup = w.workgroup_id where done_at_location = ? and stage_id in (?) order by project_id", array($done_at_location, ProNav_Utils::STAGE_OPEN));  
    }

    public static function ActiveProjectsCountAtLocation($done_at_location, $project_id)
    {           
        return count(Application_Model_Projects::ActiveProjectsAtLocation($done_at_location, $project_id));
    }

    public static function ActiveProjectsAtLocation($done_at_location, $project_id){
        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->fetchAll("select p.project_id, p.title, w.name, s.title stage from projects p inner join workgroups w on p.done_by_workgroup = w.workgroup_id inner join project_stages s on p.stage_id=s.stage_id where done_at_location = ? and p.stage_id not in (?, ?) order by s.title, project_id", array($done_at_location, ProNav_Utils::STAGE_CANCELLED, ProNav_Utils::STAGE_CLOSED));
    }

    public static function setFavorite($project_id, $set = true){

        $db = Zend_Db_Table::getDefaultAdapter();
        return $db->update('project_users', array(
            'favorite' => ($set ? 1 : 0)
            ), 
            ($db->quoteInto('user_id = ? AND ', ProNav_Auth::getUserID()).$db->quoteInto(' project_id = ?', $project_id))
        );              
    }           
}