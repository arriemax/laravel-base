<?php

class OrganizationUnitsController extends \BaseController
{

    /**
     * Display a listing of organizationunits
     *
     * @return Response
     */
    public function index()
    {
        if (!OrganizationUnit::canList()) {
            return $this->_access_denied();
        }
        if (Request::ajax()) {
            $organization_units = OrganizationUnit::with('user', 'users')
                ->select(['organization_units.id', 'organization_units.name', 'parent.name as parent_name', 'organization_units.user_id', 'organization_units.id as user_count', 'organization_units.id as actions'])
                ->leftJoin('organization_units as parent', 'organization_units.parent_id', '=', 'parent.id');
            return Datatables::of($organization_units)
                ->edit_column('actions', function($organization_unit){
                    $actions   = [];
                    $actions[] = $organization_unit->canShow() ? link_to_action('organizationunits.show', 'Show', $organization_unit->id, ['class' => 'btn btn-xs btn-primary'] ) : '';
                    $actions[] = $organization_unit->canUpdate() ? link_to_action('organizationunits.edit', 'Update', $organization_unit->id, ['class' => 'btn btn-xs btn-default'] ) : '';
                    $actions[] = $organization_unit->canDelete() ? Former::open(action('organizationunits.destroy', $organization_unit->id))->class('form-inline') 
                        . Former::hidden('_method', 'DELETE')
                        . '<button type="button" class="btn btn-danger btn-xs confirm-delete">Delete</button>'
                        . Former::close() : '';
                    return implode(' ', $actions);
                })
                ->edit_column('user_count', function($organization_unit){
                    return $organization_unit->users->count();
                })
                ->edit_column('user_id', function($organization_unit){
                    return $organization_unit->user->first_name . ' ' . $organization_unit->user->last_name;
                })
                ->remove_column('id')
                ->make();
        }
        Asset::push('js', 'datatables');
        return View::make('organizationunits.index');
    }

    /**
     * Show the form for creating a new organizationunit
     *
     * @return Response
     */
    public function create()
    {
        if (Request::ajax()) {
            return _ajax_denied();
        }
        if (!OrganizationUnit::canCreate()) {
            return $this->_access_denied();
        }
        return View::make('organizationunits.create');
    }

    /**
     * Store a newly created organizationunit in storage.
     *
     * @return Response
     */
    public function store()
    {
        $validator = Validator::make($data = Input::all(), OrganizationUnit::$rules['store']);
        if (!OrganizationUnit::canCreate()) {
            return $this->_access_denied();
        }
        if ($validator->fails()) {
            return $this->_validation_error($validator->messages());
        }
        $organizationunit = OrganizationUnit::create($data);
        if (!isset($organizationunit->id)) {
            return $this->_create_error();
        }
        $parent = OrganizationUnit::findOrFail($data['parent_id']);
        $organizationunit->makeChildOf($parent);
        $parent->touch();
        if (Request::ajax()) {
            return Response::json($organizationunit->toJson(), 201);
        }
        return Redirect::route('organizationunits.index')
            ->with('notification:success', $this->created_message);
    }

    /**
     * Display the specified organizationunit.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $organizationunit = OrganizationUnit::findOrFail($id);
        if (!$organizationunit->canShow()) {
            return $this->_access_denied();
        }
        if (Request::ajax()) {
            return $organizationunit;
        }
        Asset::push('js', 'show');
        return View::make('organizationunits.show', compact('organizationunit'));
    }

    /**
     * Show the form for editing the specified organizationunit.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        $organizationunit = OrganizationUnit::find($id);
        if (Request::ajax()) {
            return _ajax_denied();
        }
        if (!$organizationunit->canUpdate()) {
            return $this->_access_denied();
        }
        return View::make('organizationunits.edit', compact('organizationunit'));
    }

    /**
     * Update the specified organizationunit in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id)
    {
        $organizationunit = OrganizationUnit::findOrFail($id);
        if (!$organizationunit->canUpdate()) {
            return $this->_access_denied();
        }
        $validator = Validator::make($data = Input::all(), OrganizationUnit::$rules['update']);
        if ($validator->fails()) {
            return $this->_validation_error($validator->messages());
        }
        if (!$organizationunit->update($data)) {
            return $this->_update_error();
        }
        if ((int) $organizationunit->parent_id !== (int) $data['parent_id']) {
            $organizationunit->makeChildOf($data['parent_id']);
            self::find($data['parent_id'])->touch();
        }
        if (Request::ajax()) {
            return $organizationunit;
        }

        Session::remove('_old_input');
        return Redirect::route('organizationunits.edit', $id)
            ->with('notification:success', $this->updated_message);
    }

    /**
     * Remove the specified organizationunit from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $organizationunit = OrganizationUnit::findOrFail($id);
        if (!$organizationunit->canDelete()) {
            return $this->_access_denied();
        }
        if (!$organizationunit->delete()) {
            return $this->_delete_error();
        }
        if (Request::ajax()) {
            return Response::json($this->deleted_message);
        }
        return Redirect::route('organizationunits.index')
            ->with('notification:success', $this->deleted_message);
    }

    public function __construct()
    {
        parent::__construct();
        View::share('controller', 'OrganizationUnitsController');
    }
}
