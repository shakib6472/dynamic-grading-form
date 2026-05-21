<?php
/**
 * Field option definitions (single source of truth).
 *
 * @package DDA_Incident_Report
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DDA_Incident_Report_Fields {

	public static function serious_reportable_options() {
		return array(
			'abuse'                           => 'Abuse',
			'death'                           => 'Death',
			'exploitation'                    => 'Exploitation',
			'inappropriate_restraints_injury' => 'Inappropriate use of approved restraints which results in serious injury',
			'missing_person'                  => 'Missing person',
			'neglect'                         => 'Neglect',
			'repeated_restrictive_controls'   => 'Repeated emergency use of restrictive controls',
			'serious_medication_error'        => 'Serious medication error',
			'serious_physical_injury'         => 'Serious physical injury',
			'suicide_attempt'                 => 'Suicide attempt',
			'unapproved_restraints'           => 'Use of unapproved restraints',
			'emergency_hospitalization'       => 'Unplanned or emergency inpatient hospitalization',
		);
	}

	public static function abuse_neglect_categories() {
		return array(
			'physical'      => 'Physical',
			'verbal'        => 'Verbal',
			'sexual'        => 'Sexual',
			'psychological' => 'Psychological',
			'mistreatment'  => 'Mistreatment',
		);
	}

	public static function reportable_options() {
		return array(
			'emergency_relocation'      => 'Emergency relocation',
			'er_urgent_care'            => 'Emergency room or urgent care visit',
			'emergency_unauth_controls' => 'Emergency unauthorized use of restrictive controls',
			'fire'                      => 'Fire',
			'inappropriate_restraints'  => 'Inappropriate use of approved restraints (no injury)',
			'police_involvement'        => 'Incidents involving the police',
			'medication_error'          => 'Medication error',
			'physical_injury'           => 'Physical injury',
			'property_destruction'      => 'Property destruction',
			'suicide_threat'            => 'Suicide threat',
			'vehicle_accident'          => 'Vehicle accident',
		);
	}

	public static function location_types() {
		return array(
			'facility_home'           => 'Facility/Home',
			'apartment_home'          => 'Apartment Home',
			'natural_home'            => 'Natural Home',
			'supported_employment'    => 'Supported Employment',
			'day_program'             => 'Day Program',
			'vacation'                => 'Vacation',
			'provider_transportation' => 'Provider\'s transportation',
			'mtm_transportation'      => 'MTM transportation vehicle',
			'public_transportation'   => 'Public transportation',
			'hospital'                => 'Hospital',
			'nursing_home'            => 'Nursing Home',
		);
	}

	public static function residential_provider_types() {
		return array(
			'residential_habilitation' => 'Residential Habilitation',
			'natural_home'             => 'Natural Home',
			'supported_living'         => 'Supported Living',
			'icf'                      => 'ICF',
			'respite'                  => 'Respite',
			'host_home'                => 'Host Home',
		);
	}

	public static function notification_recipients() {
		return array(
			'dds_service_coordinator' => 'DDS Service Coordinator',
			'dds_duty_officer'        => 'DDS Duty Officer',
			'police_911'              => '911 - Police Department',
			'ems_911'                 => '911 - Emergency Medical Services (EMS)',
			'medical_examiner'        => 'Chief Medical Examiner (ALL DEATHS)',
			'pcp'                     => 'Person\'s Primary Care Physician (PCP)',
			'family_guardian'         => 'Person\'s Family/Guardian',
			'legal_rep'               => 'Person\'s Legal Representative/Attorney',
			'aps'                     => 'Adult Protective Services (APS)',
			'doh_hrla'                => 'DOH/Health Regulations and Licensing Administration',
			'don_rn_lpn'              => 'Provider\'s Director of Nursing/Registered Nurse/LPN',
			'supervisor_qiddp'        => 'Provider\'s Supervisor/Manager/QIDDP for Incident Location',
			'provider_ceo'            => 'Provider\'s CEO/Administration/Program Manager',
			'imc'                     => 'Provider\'s Incident Management Coordinator (IMC)',
		);
	}
}
