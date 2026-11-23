<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminMfaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookLookupController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\CommunityInvitationController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReadingController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\AdminSecurityMiddleware;
use Illuminate\Support\Facades\Route;

// Public Landing Page & Catalog
Route::get('/', [HomeController::class, 'landing'])->name('landing');
Route::get('/explore', [ExploreController::class, 'index'])->name('explore');
Route::get('/search', [SearchController::class, 'index'])->name('search.index');
Route::get('/books/search', [ExploreController::class, 'searchBooks'])->name('books.search');
Route::get('/books/{book}', [ExploreController::class, 'show'])->name('books.show');
Route::get('/libraries/books/search-existing', [LibraryController::class, 'searchExisting'])->name('libraries.searchExisting');
Route::get('/communities', [CommunityController::class, 'index'])->name('communities.index');
Route::get('/communities/join/{code}', [CommunityController::class, 'joinWithCode'])->name('communities.joinWithCode');
Route::get('/communities/invitations/{token}', [CommunityInvitationController::class, 'show'])->name('communities.invitations.show');
Route::get('/challenges', [ChallengeController::class, 'index'])->name('challenges.index');
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('/calendar/meetings/{meeting}/ics', [CalendarController::class, 'exportIcs'])->name('calendar.exportIcs');
Route::get('/calendar/meetings/{meeting}/export-ics', [CalendarController::class, 'exportIcs']);
Route::get('/calendar/meetings/{meeting}/google', [CalendarController::class, 'googleCalendar'])->name('calendar.googleCalendar');

// Authentication & Profile
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::any('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/auth/switch/{role}', [AuthController::class, 'switchUser'])->name('auth.switch');
Route::get('/auth/quick-login/{role}', [AuthController::class, 'switchUser'])->name('auth.quickLogin');

// Protected User Workspace (Requires Authentication)
Route::middleware('auth')->group(function () {
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    // Libraries & Lending
    Route::get('/libraries', [LibraryController::class, 'index'])->name('libraries.index');
    Route::post('/libraries', [LibraryController::class, 'storeLibrary'])->name('libraries.store');
    Route::post('/libraries/join-by-code', [LibraryController::class, 'joinByCode'])->name('libraries.joinByCode');
    Route::post('/libraries/{library}/members', [LibraryController::class, 'addMember'])->name('libraries.addMember');
    Route::put('/libraries/{library}/members/{member}', [LibraryController::class, 'updateMemberRole'])->name('libraries.updateMemberRole');
    Route::delete('/libraries/{library}/members/{member}', [LibraryController::class, 'removeMember'])->name('libraries.removeMember');
    Route::post('/libraries/{library}/transfer-ownership', [LibraryController::class, 'transferOwnership'])->name('libraries.transferOwnership');
    Route::put('/libraries/{library}/settings', [LibraryController::class, 'updateSettings'])->name('libraries.updateSettings');
    Route::delete('/libraries/{library}/permanent', [LibraryController::class, 'forceDestroy'])->name('libraries.forceDestroy');
    Route::delete('/libraries/{library}', [LibraryController::class, 'destroy'])->name('libraries.destroy');

    Route::post('/libraries/books', [LibraryController::class, 'storeBook'])->name('libraries.storeBook');
    Route::get('/books/isbn/{isbn}', [BookLookupController::class, 'isbn'])->middleware('throttle:30,1')->name('books.isbn');
    Route::post('/libraries/add-to-personal', [LibraryController::class, 'addToPersonal'])->name('libraries.addToPersonal');
    Route::post('/libraries/copies/{copy}/condition', [LibraryController::class, 'updateCopyCondition'])->name('libraries.updateCopyCondition');
    Route::post('/libraries/copies/{copy}/loan', [LibraryController::class, 'requestLoan'])->name('libraries.requestLoan');
    Route::delete('/libraries/copies/{copy}', [LibraryController::class, 'destroyCopy'])->name('libraries.destroyCopy');
    Route::post('/libraries/loans/{loan}/status', [LibraryController::class, 'updateLoanStatus'])->name('libraries.updateLoanStatus');
    Route::post('/libraries/loans/{loan}/extend', [LibraryController::class, 'extendLoan'])->name('libraries.extendLoan');
    Route::post('/libraries/loans/{loan}/handshake', [LibraryController::class, 'handshake'])->name('libraries.handshake');
    Route::get('/libraries/loans/{loan}/condition-report', [LibraryController::class, 'conditionReport'])->name('libraries.conditionReport');
    Route::post('/libraries/copies/{copy}/passport-note', [LibraryController::class, 'addPassportNote'])->name('libraries.addPassportNote');

    // Waitlists
    Route::post('/libraries/{library}/books/{book}/waitlist', [LibraryController::class, 'joinWaitlist'])->name('libraries.joinWaitlist');
    Route::delete('/libraries/waitlists/{waitlist}', [LibraryController::class, 'cancelWaitlist'])->name('libraries.cancelWaitlist');

    // Reading Journey, Sessions, Reviews & Goals
    Route::get('/reading', [ReadingController::class, 'index'])->name('reading.index');
    Route::post('/reading/sessions', [ReadingController::class, 'storeSession'])->name('reading.storeSession');
    Route::post('/reading/reflections', [ReadingController::class, 'storeReflection'])->name('reading.storeReflection');
    Route::post('/reading/reviews', [ReadingController::class, 'storeReview'])->name('reading.storeReview');
    Route::post('/reading/reviews/{review}/like', [ReadingController::class, 'likeReview'])->name('reading.likeReview');
    Route::post('/reading/switch-book', [ReadingController::class, 'switchCurrentBook'])->name('reading.switchBook');
    Route::post('/reading/books/status', [ReadingController::class, 'updateBookStatus'])->name('reading.updateBookStatus');
    Route::post('/reading/goal', [ReadingController::class, 'updateGoal'])->name('reading.updateGoal');

    // Community Actions, Settings, Challenges & Announcements
    Route::post('/communities', [CommunityController::class, 'store'])->name('communities.store');
    Route::put('/communities/{community}/settings', [CommunityController::class, 'updateSettings'])->name('communities.updateSettings');
    Route::post('/communities/{community}/import-members', [CommunityController::class, 'importMembers'])->name('communities.importMembers');
    Route::post('/communities/{community}/challenges', [CommunityController::class, 'storeChallenge'])->name('communities.storeChallenge');
    Route::post('/communities/{community}/meetings', [CommunityController::class, 'storeMeeting'])->name('communities.storeMeeting');
    Route::post('/communities/{community}/toggle', [CommunityController::class, 'toggleMembership'])->name('communities.toggle');
    Route::post('/communities/join-by-code', [CommunityController::class, 'joinByCode'])->name('communities.joinByCode');
    Route::post('/communities/{community}/invite', [CommunityController::class, 'sendInvitation'])->name('communities.sendInvitation');
    Route::post('/communities/{community}/invitations', [CommunityInvitationController::class, 'store'])->name('communities.invitations.store');
    Route::post('/communities/invitations/{token}/accept', [CommunityInvitationController::class, 'accept'])->name('communities.invitations.accept');
    Route::post('/communities/invitations/{token}/reject', [CommunityInvitationController::class, 'reject'])->name('communities.invitations.reject');
    Route::post('/communities/invitations/{invitation}/revoke', [CommunityInvitationController::class, 'revoke'])->name('communities.invitations.revoke');
    Route::post('/communities/{community}/join-request', [CommunityController::class, 'requestJoin'])->name('communities.requestJoin');
    Route::post('/communities/join-requests/{request}/approve', [CommunityController::class, 'approveJoinRequest'])->name('communities.approveJoinRequest');
    Route::post('/communities/join-requests/{request}/reject', [CommunityController::class, 'rejectJoinRequest'])->name('communities.rejectJoinRequest');
    Route::post('/communities/{community}/announcements', [CommunityController::class, 'storeAnnouncement'])->name('communities.storeAnnouncement');
    Route::post('/communities/{community}/highlights', [CommunityController::class, 'storeCommunityHighlight'])->name('communities.highlights.store');
    Route::post('/communities/{community}/discussions', [CommunityController::class, 'storeDiscussion'])->name('communities.storeDiscussion');
    Route::get('/communities/{community}/books/{book}/discussions', [CommunityController::class, 'bookDiscussions'])->name('communities.bookDiscussions');
    Route::post('/discussions/{discussion}/comments', [CommunityController::class, 'storeComment'])->name('discussions.storeComment');
    Route::post('/discussions/{discussion}/like', [CommunityController::class, 'toggleDiscussionLike'])->name('discussions.like');
    Route::post('/comments/{comment}/like', [CommunityController::class, 'toggleCommentLike'])->name('comments.like');
    Route::post('/reflections/{reflection}/like', [CommunityController::class, 'toggleReflectionLike'])->name('reflections.like');
    Route::post('/discussions/{discussion}/pin', [CommunityController::class, 'toggleDiscussionPin'])->name('discussions.pin');
    Route::post('/content/report', [CommunityController::class, 'reportContent'])->name('content.report');
    Route::post('/moderation/reports/{report}/resolve', [CommunityController::class, 'resolveReport'])->name('moderation.resolveReport');
    Route::post('/polls/{poll}/vote', [CommunityController::class, 'votePoll'])->name('polls.vote');
    Route::post('/polls/{poll}/close-and-set-winner', [CommunityController::class, 'closePollAndSetWinner'])->name('polls.closeAndSetWinner');
    Route::post('/communities/{community}/nominations', [CommunityController::class, 'storeNomination'])->name('communities.storeNomination');
    Route::post('/nominations/{nomination}/endorse', [CommunityController::class, 'endorseNomination'])->name('nominations.endorse');
    Route::post('/nominations/{nomination}/add-to-poll', [CommunityController::class, 'addNominationToPoll'])->name('nominations.addToPoll');
    Route::post('/communities/{community}/badges', [CommunityController::class, 'storeCommunityBadge'])->name('communities.storeBadge');
    Route::post('/communities/badges/{badge}/award', [CommunityController::class, 'awardCommunityBadge'])->name('communities.awardBadge');
    Route::post('/communities/{community}/transfer-ownership', [CommunityController::class, 'transferOwnership'])->name('communities.transferOwnership');
    Route::put('/communities/{community}/members/{member}', [CommunityController::class, 'updateMemberRole'])->name('communities.updateMemberRole');
    Route::delete('/communities/{community}/members/{member}', [CommunityController::class, 'removeMember'])->name('communities.removeMember');
    Route::delete('/communities/{community}', [CommunityController::class, 'destroy'])->name('communities.destroy');

    // Challenge Actions, Teams & Progress
    Route::post('/challenges', [ChallengeController::class, 'store'])->name('challenges.store');
    Route::post('/challenges/{challenge}/join', [ChallengeController::class, 'join'])->name('challenges.join');
    Route::post('/challenges/{challenge}/team-join', [ChallengeController::class, 'joinWithTeam'])->name('challenges.joinWithTeam');
    Route::post('/challenges/{challenge}/teams/{team}/join', [ChallengeController::class, 'joinTeam'])->name('challenges.joinTeam');
    Route::post('/challenges/{challenge}/teams', [ChallengeController::class, 'storeTeam'])->name('challenges.storeTeam');
    Route::post('/challenges/{challenge}/progress', [ChallengeController::class, 'logProgress'])->name('challenges.logProgress');
    Route::post('/challenges/{challenge}/sync-reading', [ChallengeController::class, 'syncReading'])->name('challenges.syncReading');
    Route::post('/challenges/{challenge}/status', [ChallengeController::class, 'updateStatus'])->name('challenges.updateStatus');
    Route::post('/challenges/{challenge}/notify-ending-soon', [ChallengeController::class, 'notifyEndingSoon'])->name('challenges.notifyEndingSoon');

    // Calendar & Meeting Lifecycle Actions
    Route::post('/calendar/meetings', [CalendarController::class, 'store'])->name('calendar.meetings.store');
    Route::post('/calendar/meetings/{meeting}/rsvp', [CalendarController::class, 'toggleRsvp'])->name('calendar.toggleRsvp');
    Route::post('/calendar/meetings/{meeting}/check-in', [CalendarController::class, 'checkIn'])->name('calendar.checkIn');
    Route::post('/calendar/meetings/{meeting}/minutes', [CalendarController::class, 'storeMinutes'])->name('calendar.storeMinutes');
    Route::post('/calendar/meetings/{meeting}/post-details', [CalendarController::class, 'storePostMeetingDetails'])->name('calendar.storePostDetails');
    Route::post('/calendar/meetings/{meeting}/rating', [CalendarController::class, 'storeRating'])->name('calendar.storeRating');
    Route::delete('/calendar/meetings/{meeting}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/seen', [NotificationController::class, 'markSeen'])->name('notifications.seen');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.updatePreferences');
    Route::post('/notifications/push/subscribe', [NotificationController::class, 'subscribePush'])->name('notifications.push.subscribe');
    Route::post('/notifications/push/unsubscribe', [NotificationController::class, 'unsubscribePush'])->name('notifications.push.unsubscribe');
    Route::post('/notifications/push/toggle-opt-in', [NotificationController::class, 'togglePushOptIn'])->name('notifications.push.toggleOptIn');

    // Reader Profile, Companions & Social Activity
    Route::get('/profile/{user?}', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile/follow/{user}', [ProfileController::class, 'toggleFollow'])->name('profile.toggleFollow');
    Route::post('/profile/update-bio', [ProfileController::class, 'updateBio'])->name('profile.updateBio');
    Route::post('/profile/activity-privacy', [ProfileController::class, 'updateActivityPrivacy'])->name('profile.updateActivityPrivacy');
    Route::post('/activities/{activity}/cheer', [ProfileController::class, 'cheerActivity'])->name('activities.cheer');

    // Friendships & User Blocks
    Route::post('/friends/request/{user}', [FriendshipController::class, 'sendRequest'])->name('friends.request');
    Route::post('/friends/accept/{user}', [FriendshipController::class, 'acceptRequest'])->name('friends.accept');
    Route::post('/friends/reject/{user}', [FriendshipController::class, 'rejectRequest'])->name('friends.reject');
    Route::post('/friends/unfriend/{user}', [FriendshipController::class, 'unfriend'])->name('friends.unfriend');
    Route::post('/friends/block/{user}', [FriendshipController::class, 'blockUser'])->name('friends.block');
    Route::post('/friends/unblock/{user}', [FriendshipController::class, 'unblockUser'])->name('friends.unblock');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // Super Admin Dashboard & Management Center
    Route::prefix('admin')->middleware([AdminSecurityMiddleware::class])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
        Route::post('/users/{user}/role', [AdminDashboardController::class, 'updateUserRole'])->name('admin.updateRole');
        Route::post('/users/{user}/suspension', [AdminDashboardController::class, 'toggleSuspension'])->name('admin.toggleSuspension');
        Route::delete('/users/{user}', [AdminDashboardController::class, 'destroyUser'])->name('admin.destroyUser');
        Route::delete('/content/{type}/{id}', [AdminDashboardController::class, 'deleteContent'])->name('admin.deleteContent');
        Route::post('/reports/{report}/action', [AdminDashboardController::class, 'handleReportAction'])->name('admin.handleReport');
        Route::post('/settings', [AdminDashboardController::class, 'updateSettings'])->name('admin.updateSettings');

        // Admin Multi-Factor Authentication (MFA)
        Route::get('/mfa/verify', [AdminMfaController::class, 'showVerify'])->name('admin.mfa.verify');
        Route::post('/mfa/verify', [AdminMfaController::class, 'verify'])->name('admin.mfa.verify.post');
        Route::get('/mfa/setup', [AdminMfaController::class, 'showSetup'])->name('admin.mfa.setup');
        Route::post('/mfa/enable', [AdminMfaController::class, 'enable'])->name('admin.mfa.enable');
        Route::post('/mfa/disable', [AdminMfaController::class, 'disable'])->name('admin.mfa.disable');
    });
});
