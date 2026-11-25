<?php

return [
    'success' => 'Operation completed successfully.',
    'error' => 'An error occurred while processing your request.',
    'created' => 'Resource created successfully.',
    'updated' => 'Resource updated successfully.',
    'deleted' => 'Resource deleted successfully.',

    // Auth specific
    'registered_successfully' => 'Account created successfully.',
    'logged_in_successfully' => 'Logged in successfully.',
    'logged_out_successfully' => 'Logged out successfully.',
    'account_inactive' => 'This account is inactive. Please contact support.',
    'invalid_credentials' => 'The provided credentials are incorrect.',
    'unauthenticated' => 'Authentication is required to access this resource.',
    'unauthorized' => 'You do not have permission to perform this action.',
    'not_found' => 'The requested resource was not found.',
    'validation_failed' => 'The given data was invalid.',
    'too_many_requests' => 'Too many attempts. Please try again later.',
    'server_error' => 'An unexpected server error occurred. Please try again later.',

    // Installations & Reviews
    'installed_successfully' => 'PWA installed successfully.',
    'already_installed' => 'PWA is already installed.',
    'review_added_successfully' => 'Your review has been submitted successfully.',
    'review_updated_successfully' => 'Your review has been updated successfully.',
    'review_deleted_successfully' => 'Review deleted successfully.',
    'cannot_review_own_pwa' => 'You cannot review your own application.',
    'already_reviewed' => 'You have already submitted a review for this application.',
    'install_required_for_review' => 'You must install the application before submitting a review.',

    // Developer specific
    'pwa_submitted_successfully' => 'Application submitted for review successfully.',
    'pwa_updated_successfully' => 'Application details updated successfully.',
    'pwa_resubmitted_successfully' => 'Application resubmitted for review successfully.',
    'pwa_manifest_exists' => 'You already have an application registered with this Manifest URL.',
    'max_pending_pwas_reached' => 'You have reached the maximum number of pending applications (:max).',
    'cannot_update_approved_pwa' => 'Approved applications cannot be modified in the current version.',
    'cannot_update_pending_pwa' => 'Applications pending review cannot be modified.',
    'cannot_resubmit_pwa' => 'Only rejected or draft applications can be resubmitted for review.',
    'dashboard_retrieved_successfully' => 'Dashboard data retrieved successfully.',

    // Moderator specific
    'pwa_approved_successfully' => 'Application approved and published successfully.',
    'pwa_rejected_successfully' => 'Application rejected successfully with reason recorded.',
    'pwa_not_pending' => 'This application is not currently pending review.',
    'cannot_review_own_pwa_as_moderator' => 'You cannot review or moderate an application that you own.',
];
