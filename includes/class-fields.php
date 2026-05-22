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

	/**
	 * Training scenario the learner reads before filling out the form.
	 * Shared by the front-end shortcode AND the print template, so the
	 * scenario only has one source of truth.
	 *
	 * Customise via the `dda_incident_report_scenario` filter.
	 */
	public static function scenario() {
		$default = array(
			'title'        => 'DDA Phase I Training Test: Incident Management Scenario 1',
			'instructions' => 'Read the following scenario and use the information to complete the incident report for your agency. (Note to trainers: You may use any or all of the four scenarios for this test. Pass out a blank incident report along with the scenario and develop a grading rubric to determine the grade for each person.)',
			'narrative'    => array(
				'Donna Brown is a 41 year old female who was born on March 11, 1973. Ms. Brown lives at 3330 Floral Ave., NW Washington, DC with two friends that she has known since residing at Forest Haven. On Monday, February 24, 2015 at approximately 11pm as Ms. Brown was walking to her bedroom, she spilled the tea she was carrying on her shirt. After this, she went to the restroom and began cleaning herself up. Ms. Brown\'s staff, Doreen Johnson, came into the restroom and saw Ms. Brown attempting to clean herself and started yelling. Ms. Johnson then proceeded to push Ms. Brown into her bedroom and down onto her bed, while yelling that she was making a big mess. While being pushed down on the bed, Ms. Brown hit her head on the headboard and began crying. After hearing the noise, a second staff person in the home, Muriel Blackwell, came upstairs to find Ms. Brown on the bed naked, holding her head, and crying. When Ms. Blackwell asked what happened, Ms. Brown removed her hand from her head exposing a big gash just above her left temple. Ms. Brown told Ms. Blackwell that Ms. Johnson pushed her onto the bed and she hit her head. Ms. Johnson denied pushing Ms. Brown and said that she fell onto the bed on her own. Ms. Blackwell went downstairs and called the nurse who instructed her to take Ms. Brown to the nearest emergency room. Ms. Blackwell made the rest of the appropriate notifications. Please write the report as if you are Ms. Blackwell and be sure to document everyone Ms. Blackwell is required to notify.',
			),
			'additional'   => 'This incident was discovered.',
			'circle'       => array(
				'Administrator – Jamie Pines',
				'Attorney – Johnny Cochran',
				'Department of Health Contact – Roy Rogers',
				'Department on Disability Services Service Coordinator – Ronald McDonald',
				'Family/Guardian – James Brown',
				'Incident Management Coordinator – Lisa Stern',
				'Nurse – Jackie Jones',
				'Program Coordinator/House Manager – Elton John',
				'Program Director – Tina Turner',
				'Support Coordinator/QIDP – Michael Jackson',
			),
		);

		/**
		 * Filter the training scenario shown on the form and printed
		 * before the incident report.
		 *
		 * @param array $scenario Scenario data (title, instructions,
		 *                        narrative as string|array, additional,
		 *                        circle as array of "Role – Name" lines).
		 */
		return apply_filters( 'dda_incident_report_scenario', $default );
	}
}
