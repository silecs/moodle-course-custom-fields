<?php
class custominfo_field_extension_user implements custominfo_field_extension_i {
    protected $capability = 'moodle/user:update';

    /**
     * @return string
     */
    public function get_capability() {
        return $this->capability;
    }

    /**
     * Check if the field data is visible to the current user
     * @param object $field     StdClass object from the table custom_info_field
     * @param integer $objectid
     * @return  boolean
     */
    public function is_visible($field, $objectid) {
        global $USER;

        switch ($field->visible) {
            case CUSTOMINFO_VISIBLE_ALL:
                return true;
            case CUSTOMINFO_VISIBLE_PRIVATE:
                if ($objectid == $USER->id) {
                    return true;
                } else {
                    return has_capability('moodle/user:viewalldetails', get_context_instance(CONTEXT_USER, $objectid));
                }
            case CUSTOMINFO_VISIBLE_NONE:
            default:
                return has_capability($this->capability, get_context_instance(CONTEXT_USER, $objectid));
        }
    }
} /// End of class definition
