<?php
// Load the authentication guard.
// This MUST be at the very top.
// It checks if the user is authenticated AND is an admin.
// If not, it redirects to login.php or shows a 403 error.
require_once 'auth.php';

// Get current user details to pass to React
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232563eb' stroke-width='2'%3E%3Cpath d='M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z'/%3E%3C/svg%3E">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SCCP Configurator – Asterisk</title>
  <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
  <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
  <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { margin: 0; padding: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
    /* Add smooth transition for modal */
    .modal-enter { opacity: 0; transform: scale(0.95); }
    .modal-enter-active { opacity: 1; transform: scale(1); transition: all 200ms ease-out; }
    .modal-leave { opacity: 1; transform: scale(1); }
    .modal-leave-active { opacity: 0; transform: scale(0.95); transition: all 200ms ease-in; }
  </style>
</head>
<body class="bg-slate-900">
  <div id="root"></div>

  <script type="text/babel">
    const { useState, useEffect, useRef } = React;

    const currentUser = <?php echo json_encode($currentUser, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    /*** Icons (Same as before) ***/
    const Icon = ({ w = 20, h = 20, children }) => (
      <svg xmlns="http://www.w3.org/2000/svg" width={w} height={h} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        {children}
      </svg>
    );
    const Phone = () => <Icon w={24} h={24}><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.1.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.6 2.81.7A2 2 0 0 1 22 16.92z"/></Icon>;
    const LogOut = () => <Icon><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></Icon>;
    const UserIcon = () => <Icon><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></Icon>;
    const Plus = () => <Icon><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></Icon>;
    const Edit = () => <Icon><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></Icon>;
    const Trash = () => <Icon><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></Icon>;
    const Save = () => <Icon><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></Icon>;
    const Refresh = () => <Icon><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></Icon>;
    const CheckCircle = () => <Icon><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></Icon>;
    const XCircle = () => <Icon><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></Icon>;
    const Server = () => <Icon w={24} h={24}><rect x="2" y="2" width="20" height="8" rx="2" ry="2"/><rect x="2" y="14" width="20" height="8" rx="2" ry="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></Icon>;
    const FileText = () => <Icon><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></Icon>;
    const AlertTriangle = () => <Icon><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></Icon>;
    const Settings = () => <Icon><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"/></Icon>;
    const PhoneOutgoing = () => <Icon><polyline points="16 2 16 8 22 8"/><line x1="22" y1="2" x2="16" y2="8"/><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></Icon>;
    const Book = () => <Icon><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></Icon>;
    const Download = () => <Icon><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></Icon>;
    const Info = () => <Icon><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></Icon>;
    const ClipboardList = () => <Icon><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="15" y2="16"/></Icon>;
    const Lock = () => <Icon><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></Icon>;

    // Default state for a new phone
    const defaultPhoneState = {
      mac: '',
      model: '7960',
      description: '',
      extension: '',
      label: '',
      pin: '1234',
      context: 'from-internal',
      department: '',
      mac2: '',
      fwd_number: '',
      fwd_timeout: '30'
    };

    // All available phone models
    const phoneModels = ['7940', '7960', '7970', '7975', '8941', '8945', '7906', '7911', '7912', '7941', '7942', '7945', '7961', '7962', '7965'];
    
    // Models with XML support
    const xmlSupportedModels = ['7940', '7960', '7970', '7975', '8941', '8945', '7941', '7942', '7945', '7961', '7962', '7965'];
    const xmlLimitedModels = ['7912', '7906', '7911'];

    /**
     * Helper: Toast component
     */
    const Toast = ({ message, onDismiss }) => {
      const isFirstRender = useRef(true);
      const [isVisible, setIsVisible] = useState(false);

      useEffect(() => {
        if (message) {
          setIsVisible(true);
          isFirstRender.current = false;
          const timer = setTimeout(() => {
            setIsVisible(false);
            onDismiss();
          }, 4000); // Give 0.5s for fade-out
          
          return () => clearTimeout(timer);
        }
      }, [message, onDismiss]);

      const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
      };
      
      return (
        <div className={`fixed top-5 right-5 z-50 p-4 rounded-lg text-white font-semibold shadow-lg transition-all duration-500 ${
          isVisible ? 'opacity-100 translate-x-0' : 'opacity-0 translate-x-full'
        } ${colors[message?.type] || 'bg-gray-800'}`}>
          {message?.text}
        </div>
      );
    };

    /**
     * Helper: Confirmation Modal
     */
    const ConfirmModal = ({ title, message, confirmText, cancelText, onConfirm, onCancel, show }) => {
      if (!show) return null;
      
      return (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 modal-enter-active">
          <div className="bg-white rounded-lg p-6 max-w-sm w-full modal-enter-active">
            <h3 className="text-xl font-bold mb-4">{title}</h3>
            <p className="text-gray-600 mb-6">{message}</p>
            <div className="flex gap-3 justify-end">
              <button
                onClick={onCancel}
                className="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-semibold"
              >
                {cancelText}
              </button>
              <button
                onClick={onConfirm}
                className="bg-red-500 text-white px-4 py-2 rounded-lg hover:bg-red-600 font-semibold"
              >
                {confirmText}
              </button>
            </div>
          </div>
        </div>
      );
    };

    /**
     * Helper: Status Label
     */
    const StatusLabel = ({ status }) => {
      const normalizedStatus = (status || 'unknown').toLowerCase();
      const isOnline = normalizedStatus === 'online' || normalizedStatus === 'registered' || normalizedStatus === 'ok';
      
      let text;
      if (isOnline) {
        text = 'Online';
      } else if (normalizedStatus === 'unknown') {
        text = 'Unknown';
      } else {
        text = 'Offline';
      }
      
      const colorClasses = isOnline 
        ? 'bg-green-100 text-green-700' 
        : (normalizedStatus === 'unknown' ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700');
      
      const dotClasses = isOnline
        ? 'bg-green-500'
        : (normalizedStatus === 'unknown' ? 'bg-gray-400' : 'bg-red-500');

      return (
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold ${colorClasses}`}>
          <span className={`w-2 h-2 rounded-full ${dotClasses}`}></span>
          {text}
        </span>
      );
    };
    
    /**
     * Helper: Phone Edit/Add Modal
     */
    const PhoneModal = ({ phone, onSave, onClose }) => {
      const [formData, setFormData] = useState(phone || defaultPhoneState);
      const isNew = !phone.mac; // Check if this is a new phone

      useEffect(() => {
        setFormData(phone);
      }, [phone]);
      
      const handleChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
      };
      
      // Auto-fill label from extension
      const handleExtensionChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
          ...prev,
          [name]: value,
          // If description is empty or matches the *old* extension, auto-fill Device Description (not Label)
          description: (prev.description === '' || prev.description === prev.extension) ? value : prev.description
        }));
      };
      
	const formatMAC = (mac) => {
	  if (!mac) return '';

 	 // 1. Convert to uppercase
 	 let clean = mac.toUpperCase();

	  // 2. Remove existing SEP prefix if present
 	 if (clean.startsWith('SEP')) {
   	 clean = clean.slice(3);
 	 }

 	 // 3. Remove all non-hex characters (: - space etc.)
 	 clean = clean.replace(/[^0-9A-F]/g, '');

	  // 4. Ensure exactly 12 hex characters (take last 12 if longer)
 	 clean = clean.slice(-12);

 	 // 5. Return Cisco SCCP format
 	 return 'SEP' + clean;
	};

	const handleMACBlur = (e) => {
 	 setFormData(prev => ({
   	 ...prev,
   	 mac: formatMAC(e.target.value)
	  }));
	};

	const handleSubmit = (e) => {
 	 e.preventDefault();
 	 onSave(formData);
	};

	return (
        <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50 modal-enter-active">
          <div className="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto modal-enter-active">
            <h3 className="text-xl font-bold mb-6">{isNew ? 'Add New Phone' : 'Edit Phone'}</h3>
            <form onSubmit={handleSubmit} className="space-y-4">
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="font-semibold text-gray-700 col-span-full pb-2 border-b">Device Info</div>
                <div>
                  <label className="block text-sm font-semibold mb-1">MAC Address</label>
                  <input
                    type="text"
                    name="mac"
                    value={formData.mac}
                    onChange={handleChange}
                    onBlur={handleMACBlur}
                    placeholder="SEP001122334455"
                    required
                    readOnly={!isNew}
                    className={`w-full px-3 py-2 border rounded-lg ${!isNew ? 'bg-gray-100' : ''}`}
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold mb-1">Phone Model</label>
                  <select
                    name="model"
                    value={formData.model}
                    onChange={handleChange}
                    className="w-full px-3 py-2 border rounded-lg"
                  >
                    {phoneModels.map(model => <option key={model} value={model}>Cisco {model}</option>)}
                  </select>
                </div>
                <div className="col-span-full">
                  <label className="block text-sm font-semibold mb-1">Second MAC Address <span className="text-gray-400 font-normal">(optional — shared line)</span></label>
                  <input
                    type="text"
                    name="mac2"
                    value={formData.mac2 || ''}
                    onChange={handleChange}
                    placeholder="SEP001122334456 (leave empty if not needed)"
                    className="w-full px-3 py-2 border rounded-lg"
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="font-semibold text-gray-700 col-span-full pb-2 border-b pt-4">Line Info</div>
                <div>
                  <label className="block text-sm font-semibold mb-1">Extension</label>
                  <input
                    type="text"
                    name="extension"
                    value={formData.extension}
                    onChange={handleExtensionChange}
                    placeholder="e.g., 503"
                    required
                    // --- FIX 2: Removed readOnly={!isNew} ---
                    className={`w-full px-3 py-2 border rounded-lg`}
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold mb-1">Line Label (Caller ID Name)</label>
                  <input
                    type="text"
                    name="label"
                    value={formData.label}
                    onChange={handleChange}
                    placeholder="e.g., John Doe"
                    required
                    className="w-full px-3 py-2 border rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold mb-1">PIN</label>
                  <input
                    type="text"
                    name="pin"
                    value={formData.pin}
                    onChange={handleChange}
                    className="w-full px-3 py-2 border rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold mb-1">Context</label>
                  <input
                    type="text"
                    name="context"
                    value={formData.context}
                    onChange={handleChange}
                    className="w-full px-3 py-2 border rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold mb-1">Forward after (sec) <span className="text-gray-400 font-normal">(no answer)</span></label>
                  <input
                    type="number"
                    name="fwd_timeout"
                    value={formData.fwd_timeout || '30'}
                    onChange={handleChange}
                    placeholder="30"
                    className="w-full px-3 py-2 border rounded-lg"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold mb-1">Forward to number <span className="text-gray-400 font-normal">(optional)</span></label>
                  <input
                    type="text"
                    name="fwd_number"
                    value={formData.fwd_number || ''}
                    onChange={handleChange}
                    placeholder="e.g. 504 (empty = no forwarding)"
                    className="w-full px-3 py-2 border rounded-lg"
                  />
                </div>
              </div>
              
              <div>
                <label className="block text-sm font-semibold mb-1">Device Description</label>
                <input
                  type="text"
                  name="description"
                  value={formData.description}
                  onChange={handleChange}
                  placeholder="e.g., John Doe's Office Phone"
                  className="w-full px-3 py-2 border rounded-lg"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold mb-1">Department <span className="text-gray-400 font-normal">(optional)</span></label>
                <input
                  type="text"
                  name="department"
                  value={formData.department}
                  onChange={handleChange}
                  placeholder="e.g., IT, Reception, Management"
                  className="w-full px-3 py-2 border rounded-lg"
                />
              </div>

              <div className="flex gap-3 justify-end pt-6">
                <button
                  type="button"
                  onClick={onClose}
                  className="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold"
                >
                  {isNew ? 'Add Phone' : 'Save Changes'}
                </button>
              </div>
            </form>
          </div>
        </div>
      );
    };

    /**
     * Main App Component
     */
    const SCCPConfigurator = () => {
      const API_BASE = '/sccp-config/api.php'; // Use relative path

      // --- STATE ---
      // A single list for all provisioned phones
      const [provisionedPhones, setProvisionedPhones] = useState([]);
      
      // Status maps
      const [deviceStatus, setDeviceStatus] = useState({}); // { 'SEP...': 'online', ... }
      const [lineStatus, setLineStatus] = useState({});   // { '503': 'online', ... }

      // View State
      const [currentView, setCurrentView] = useState('phones'); // 'phones' | 'directory'

      // Directory State
      const [contacts, setContacts] = useState([]);
      const [contactsLoaded, setContactsLoaded] = useState(false);
      const [auditLog, setAuditLog] = useState([]);
      const [logLoading, setLogLoading] = useState(false);
      const [contactSaving, setContactSaving] = useState(false);
      const [showContactModal, setShowContactModal] = useState(false);
      const [editContact, setEditContact] = useState(null);  // null = add new
      const [showDeleteContactConfirm, setShowDeleteContactConfirm] = useState(null);
      const [contactSearch, setContactSearch] = useState('');
      const [phoneSearch, setPhoneSearch] = useState('');
      const [sortBy, setSortBy] = useState('name');
      const [infoPhone, setInfoPhone] = useState(null);   // phone object for info modal
      const [infoContact, setInfoContact] = useState(null); // contact object for info modal

      // UI State
      const [loading, setLoading] = useState(true);
      const [saving, setSaving] = useState(false);
      const [refreshing, setRefreshing] = useState(false);
      const [message, setMessage] = useState(null); // { text: '...', type: 'success' }

      // Modal State
      const [showPhoneModal, setShowPhoneModal] = useState(false);
      const [editPhone, setEditPhone] = useState(null); // Holds the phone object being edited
      const [showDeleteConfirm, setShowDeleteConfirm] = useState(null); // Holds MAC of phone to delete
      const [showLogoutConfirm, setShowLogoutConfirm] = useState(false);
      const [lockGranted, setLockGranted] = useState(true);
      const [lockHolder, setLockHolder] = useState(null);
      const [lockRequest, setLockRequest] = useState(null);
      const [requestSent, setRequestSent] = useState(false);
      const [configVersion, setConfigVersion] = useState(null);
      
      // Other Modals
      const [showReloadDialog, setShowReloadDialog] = useState(false);
      const [reloadResult, setReloadResult] = useState(null);
      const [showXMLSettings, setShowXMLSettings] = useState(false);
      const [showXMLResults, setShowXMLResults] = useState(false);
      const [xmlResults, setXmlResults] = useState(null);
      const [xmlSettings, setXmlSettings] = useState({
        timeFormat: '24',
        dateFormat: 'D.M.Y',
        enableDirectory: false,
        speedDials: []
      });
      
      // --- DATA LOADING & TRANSFORMATION ---

      useEffect(() => {
        loadData();
        const interval = setInterval(loadStatus, 30000); // Auto-refresh status
        return () => clearInterval(interval);
      }, []);

      // --- Edit lock (concurrency) with idle auto-release ---
      const haveLockRef = useRef(false);
      const lastActivityRef = useRef(Date.now());
      const suppressAcquireUntilRef = useRef(0);
      const wantsAccessRef = useRef(false);
      useEffect(() => {
        const IDLE_MS = 120000; // release the lock after 2 min of inactivity
        const releaseReq = () => {
          try {
            if (navigator.sendBeacon) navigator.sendBeacon(`${API_BASE}?action=lock_release`);
            else fetch(`${API_BASE}?action=lock_release`, { keepalive: true });
          } catch (e) {}
          haveLockRef.current = false;
        };
        const acquire = async () => {
          if (Date.now() < suppressAcquireUntilRef.current) return; // post-handoff cooldown
          try {
            const r = await fetch(`${API_BASE}?action=lock_acquire&active=1`);
            const d = await r.json();
            haveLockRef.current = !!d.granted;
            setLockGranted(!!d.granted);
            setLockHolder(d.holder || null);
            setLockRequest(d.request || null);
            if (d.granted) { setRequestSent(false); wantsAccessRef.current = false; }
            else if (wantsAccessRef.current) { fetch(`${API_BASE}?action=lock_request`).catch(() => {}); }
          } catch (e) {}
        };
        const bump = () => {
          lastActivityRef.current = Date.now();
          if (!haveLockRef.current) acquire(); // resume editing -> reclaim lock if free
        };
        const events = ['mousedown', 'keydown', 'touchstart'];
        events.forEach(ev => window.addEventListener(ev, bump));
        const tick = () => {
          if (Date.now() - lastActivityRef.current > IDLE_MS) {
            if (haveLockRef.current) { releaseReq(); setLockGranted(false); setLockHolder(null); }
          } else {
            acquire();
          }
        };
        acquire();
        const hb = setInterval(tick, 20000);
        window.addEventListener('beforeunload', releaseReq);
        return () => {
          clearInterval(hb);
          events.forEach(ev => window.removeEventListener(ev, bump));
          window.removeEventListener('beforeunload', releaseReq);
          releaseReq();
        };
      }, []);

      const requestAccess = async () => {
        wantsAccessRef.current = true;
        try { await fetch(`${API_BASE}?action=lock_request`); setRequestSent(true); } catch (e) {}
      };
      const handoffLock = async () => {
        try { await fetch(`${API_BASE}?action=lock_handoff`); } catch (e) {}
        haveLockRef.current = false;
        setLockGranted(false);
        setLockHolder(lockRequest);
        setLockRequest(null);
        suppressAcquireUntilRef.current = Date.now() + 15000; // let the requester grab it
      };

      const showToast = (text, type = 'info') => {
        setMessage({ text, type });
      };

      // --- DIRECTORY / CONTACTS ---

      const loadContacts = async () => {
        if (contactsLoaded) return;
        try {
          const res = await fetch(`${API_BASE}?action=contacts`);
          const data = await res.json();
          if (data.success) {
            setContacts(data.contacts || []);
            setContactsLoaded(true);
          }
        } catch (e) {
          showToast('Failed to load contacts', 'error');
        }
      };

      const saveContacts = async (newContacts) => {
        setContactSaving(true);
        try {
          const res = await fetch(`${API_BASE}?action=contacts`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contacts: newContacts })
          });
          const data = await res.json();
          if (data.success) {
            setContacts(newContacts);
            showToast(data.message || 'Directory saved', 'success');
          } else {
            showToast(data.error || 'Save failed', 'error');
          }
        } catch (e) {
          showToast('Failed to save directory', 'error');
        } finally {
          setContactSaving(false);
        }
      };

      const handleSaveContact = (contact) => {
        let updated;
        if (editContact === null) {
          // New contact
          updated = [...contacts, contact];
        } else {
          updated = contacts.map((c, i) => i === editContact._idx ? contact : c);
        }
        saveContacts(updated);
        setShowContactModal(false);
        setEditContact(null);
      };

      const handleDeleteContact = (idx) => {
        const updated = contacts.filter((_, i) => i !== idx);
        saveContacts(updated);
        setShowDeleteContactConfirm(null);
      };

      const handleSwitchToDirectory = () => {
        setCurrentView('directory');
        loadContacts();
      };

      const loadAuditLog = async () => {
        setLogLoading(true);
        try {
          const r = await fetch('api.php?action=audit_log');
          const data = await r.json();
          if (data.success) setAuditLog(data.log || []);
          else showToast('Log unavailable', 'error');
        } catch(e) {
          showToast('Failed to load log', 'error');
        } finally {
          setLogLoading(false);
        }
      };

      // --- DATA LOADING ---

      const loadData = async () => {
        setLoading(true);
        try {
          // Fetch config and contacts in parallel
          const [configRes, contactsRes] = await Promise.all([
            fetch(`${API_BASE}?action=config`),
            fetch(`${API_BASE}?action=contacts`)
          ]);
          const configData = await configRes.json();
          const contactsData = await contactsRes.json();

          if (!configData.success) {
            throw new Error(configData.error || 'Failed to load configuration');
          }

          // Build a department lookup map from contacts (by number/extension)
          const contactsArr = contactsData.contacts || [];
          setContacts(contactsArr);
          setContactsLoaded(true);
          const deptMap = new Map();
          contactsArr.forEach(c => { if (c.number) deptMap.set(c.number, c.department || ''); });

          // --- Data Merging Logic ---
          const devices = configData.data?.devices || [];
          const lines = configData.data?.lines || [];

          // Create a quick lookup map for lines
          const lineMap = new Map();
          lines.forEach(line => lineMap.set(line.id, line));

          // --- FIX 1: Merge devices and lines into the single state ---
          const extToDevices = new Map();
          devices.forEach(device => {
            let extension = '';
            if (device.button && device.button.startsWith('line,')) {
              extension = device.button.split(',')[1].trim();
            }
            if (!extToDevices.has(extension)) extToDevices.set(extension, []);
            extToDevices.get(extension).push(device);
          });

          const mergedPhones = Array.from(extToDevices.entries()).map(([extension, devs]) => {
            const device = devs[0];
            const device2 = devs[1] || null;
            const line = lineMap.get(extension) || {};

            return {
              // Device properties
              mac: device.mac,
              mac2: device2 ? device2.mac : '',
              model: device.devicetype || device.model || '7960',
              description: device.description || (line.label ? `${line.label}'s Phone` : ''),
              extension: extension,

              // Line properties
              label: line.label || '',
              pin: line.pin || '1234',
              context: line.context || 'from-internal',
              department: deptMap.get(extension) || '',
              fwd_number: line.fwd_number || '',
              fwd_timeout: line.fwd_timeout || '30',
            };
          });
          // --- END FIX 1 ---

          setProvisionedPhones(mergedPhones);
          setConfigVersion(configData.version || null);
          
          // Also load initial status
          await loadStatus();

        } catch (e) {
          showToast(e.message || 'Failed to load data', 'error');
          if (e.message.includes('Unauthorized') || e.message.includes('Session expired')) {
            setTimeout(() => window.location.href = 'login.php', 2000);
          }
        } finally {
          setLoading(false);
        }
      };
      
      const loadStatus = async (isManual = false) => {
        if (isManual) setRefreshing(true);
        try {
          const res = await fetch(`${API_BASE}?action=status`);
          const data = await res.json();
          if (data.success) {
            updateStatusMaps(data.devices, data.lines);
            if (isManual) showToast('Status refreshed', 'success');
          }
        } catch (e) {
          showToast('Failed to load status', 'error');
        } finally {
          if (isManual) setRefreshing(false);
        }
      };
      
      // Helper to update status state maps
      const updateStatusMaps = (devices, lines) => {
        const devMap = {};
        (devices || []).forEach(d => { devMap[d.mac] = d.status; });
        
        const lineMap = {};
        (lines || []).forEach(l => {
          const key = l.id || l.extension;
          if (key) lineMap[key] = l.status;
        });
        
        setDeviceStatus(devMap);
        setLineStatus(lineMap);
      };

      // --- DATA SAVING & TRANSFORMATION ---

      const saveConfig = async () => {
        setSaving(true);
        
        // --- Data Splitting Logic ---
        // Transform the single state array back into two arrays for the API
        
        const devicesApi = provisionedPhones.flatMap(p => {
          const d = [{ mac: p.mac, model: p.model, description: p.description, extension: p.extension }];
          if (p.mac2 && p.mac2.trim()) {
            d.push({ mac: p.mac2, model: p.model, description: (p.description || '') + ' (2)', extension: p.extension });
          }
          return d;
        });
        
        const linesApi = provisionedPhones
          .filter(p => p.extension) // Only create lines for phones that have an extension
          .map(p => ({
            id: p.extension,
            label: p.label,
            pin: p.pin,
            context: p.context,
            description: p.label, // Use label for description as well
            fwd_number: p.fwd_number || '',
            fwd_timeout: p.fwd_timeout || '30'
          }));
        
        // --- End of Splitting Logic ---

        try {
          const res = await fetch(API_BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ devices: devicesApi, lines: linesApi, version: configVersion })
          });
          const data = await res.json();
          if (data.success) {
            if (data.version) setConfigVersion(data.version);
            setReloadResult(data.sccp_reload || data); // Handle both old/new save/reload responses
            setShowReloadDialog(true);
            showToast('Configuration saved successfully', 'success');
            setTimeout(() => loadStatus(true), 1500); // Refresh status after save
          } else {
            showToast(data.error || 'Save failed', 'error');
          }
        } catch (e) {
          showToast(e.message || 'Save failed', 'error');
        } finally {
          setSaving(false);
        }
      };

      // --- ACTION HANDLERS ---
      
      const handleOpenAddModal = () => {
        setEditPhone(defaultPhoneState); // Set modal to a blank new phone
        setShowPhoneModal(true);
      };
      
      const handleOpenEditModal = (phone) => {
        setEditPhone(phone); // Set modal to the phone we clicked
        setShowPhoneModal(true);
      };
      
      const handleCloseModal = () => {
        setShowPhoneModal(false);
        setEditPhone(null); // Clear editing state
      };
      
      const handleSavePhone = (phoneToSave) => {
        if (!phoneToSave.mac || !phoneToSave.extension || !phoneToSave.label) {
          showToast('MAC, Extension, and Label are required.', 'error');
          return;
        }
        
        const formattedMAC = formatMAC(phoneToSave.mac);
        if (formattedMAC.length !== 15) {
           showToast('MAC address must contain 12 hexadecimal characters', 'error');
           return;
        }
        
        const finalPhone = { ...phoneToSave, mac: formattedMAC };
        
        // Check if this is a new phone (by checking if mac is already in use)
        const isNew = !provisionedPhones.some(p => p.mac === finalPhone.mac);
        
        // --- FIX 3: Check for duplicate extensions ---
        // Check if another phone (different MAC) is already using this extension
        if (provisionedPhones.some(
              p => p.mac !== finalPhone.mac && p.extension === finalPhone.extension
        )) {
          showToast('This extension number is already in use by another device.', 'error');
          return;
        }

        if (isNew) {
          // Add new phone
          setProvisionedPhones(prev => [...prev, finalPhone]);
        } else {
          // Update existing phone
          setProvisionedPhones(prev => prev.map(p =>
            p.mac === finalPhone.mac ? finalPhone : p
          ));
        }
        // --- END FIX 3 ---

        // Sync department to contacts.json (upsert by extension)
        if (finalPhone.extension) {
          setContacts(prev => {
            const existing = prev.findIndex(c => c.number === finalPhone.extension);
            let updated;
            if (existing >= 0) {
              updated = prev.map((c, i) => i === existing ? { ...c, name: finalPhone.label || c.name, department: finalPhone.department || '' } : c);
            } else {
              updated = [...prev, {
                name: finalPhone.label || finalPhone.extension,
                number: finalPhone.extension,
                department: finalPhone.department || ''
              }];
            }
            saveContacts(updated);
            return updated;
          });
        }

        handleCloseModal();
        showToast(isNew ? 'Phone added' : 'Phone updated', 'success');
      };

      const handleDeletePhone = () => {
        if (!showDeleteConfirm) return;
        
        setProvisionedPhones(prev => prev.filter(p => p.mac !== showDeleteConfirm));
        setShowDeleteConfirm(null);
        showToast('Phone deleted', 'success');
      };

      const handleLogout = () => {
        // Robust "fire-and-forget" logout
        setShowLogoutConfirm(false);
        try {
          const fd = new FormData();
          fd.append('action', 'logout');
          fetch('auth.php', {
            method: 'POST', body: fd, keepalive: true
          }).catch(error => {
            console.error("Logout request failed (but redirecting anyway):", error);
          });
        } catch (error) {
           console.error("Error preparing logout fetch:", error);
        }
        // Redirect always
        setTimeout(() => {
          window.location.href = 'login.php';
        }, 100);
      };

      // --- OTHER ACTIONS (Reload, XML, etc. - Unchanged) ---
      
      const manualReload = async () => {
        setSaving(true);
        try {
          const res = await fetch(`${API_BASE}?action=reload`, { method: 'PUT' });
          const data = await res.json();
          setReloadResult(data || null);
          setShowReloadDialog(true);
          if (data?.success) {
            showToast('SCCP and Dialplan reloaded', 'success');
            setTimeout(() => loadStatus(true), 1500);
          } else {
            showToast(data?.message || 'Reload failed', 'error');
          }
        } catch (e) {
          showToast('Reload failed', 'error');
        } finally {
          setSaving(false);
        }
      };

      const generateXML = async () => {
        setSaving(true);
        
        // Split data for API
        const devicesApi = provisionedPhones.flatMap(p => {
          const d = [{ mac: p.mac, model: p.model, description: p.description, extension: p.extension }];
          if (p.mac2 && p.mac2.trim()) {
            d.push({ mac: p.mac2, model: p.model, description: (p.description || '') + ' (2)', extension: p.extension });
          }
          return d;
        });
        const linesApi = provisionedPhones
          .filter(p => p.extension)
          .map(p => ({
            id: p.extension, label: p.label, pin: p.pin, context: p.context, description: p.label, fwd_number: p.fwd_number || '', fwd_timeout: p.fwd_timeout || '30'
          }));

        try {
          const res = await fetch(`${API_BASE}?action=generate-xml`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ devices: devicesApi, lines: linesApi, xmlSettings })
          });
          const data = await res.json();
          if (data?.success) {
            setXmlResults(data);
            setShowXMLResults(true);
            showToast('XML files generated successfully', 'success');
          } else {
            showToast(data?.error || 'XML generation failed', 'error');
          }
        } catch (e) {
          showToast('XML generation failed', 'error');
        } finally {
          setSaving(false);
          setShowXMLSettings(false); // Close settings modal
        }
      };

	// --- HELPERS ---

	const formatMAC = (mac) => {
 	 if (!mac) return '';

	  // Convert to uppercase
 	 let clean = mac.toUpperCase();

 	 // Remove SEP prefix if user entered it
 	 if (clean.startsWith('SEP')) {
   	 clean = clean.slice(3);
 	 }

	  // Remove everything except hexadecimal characters
 	 clean = clean.replace(/[^0-9A-F]/g, '');

	  // Ensure exactly 12 characters (real MAC)
 	 clean = clean.slice(-12);

	  // Return Cisco SCCP format
 	 return 'SEP' + clean;
	};

	const getModelSupport = (model) => {
 	 if (xmlSupportedModels.includes(model)) return 'full';
 	 if (xmlLimitedModels.includes(model)) return 'limited';
 	 return 'unknown';
	};

      // --- RENDER ---

      if (loading) {
        return (
          <div className="min-h-screen flex items-center justify-center bg-slate-900">
            <div className="text-white text-lg flex items-center gap-3">
              <Refresh w={24} h={24} className="animate-spin" />
              Loading Configuration...
            </div>
          </div>
        );
      }

      return (
        <div className="min-h-screen bg-gradient-to-br from-slate-900 via-blue-900 to-slate-900 p-3 sm:p-6">
          <Toast message={message} onDismiss={() => setMessage(null)} />
          <ConfirmModal
            show={showLogoutConfirm}
            title="Log Out"
            message="Are you sure you want to log out?"
            confirmText="Log Out"
            cancelText="Cancel"
            onConfirm={handleLogout}
            onCancel={() => setShowLogoutConfirm(false)}
          />
          <ConfirmModal
            show={!!showDeleteConfirm}
            title="Delete Phone"
            message={`Are you sure you want to delete this phone (${showDeleteConfirm})? This cannot be undone.`}
            confirmText="Delete"
            cancelText="Cancel"
            onConfirm={handleDeletePhone}
            onCancel={() => setShowDeleteConfirm(null)}
          />
          {showPhoneModal && (
            <PhoneModal
              phone={editPhone}
              onSave={handleSavePhone}
              onClose={handleCloseModal}
            />
          )}
          
          <div className="max-w-[1536px] mx-auto">
            {/* Header */}
            <div className="bg-gradient-to-r from-blue-600 to-blue-800 rounded-xl shadow-2xl p-4 sm:p-6 mb-6">
              <div className="flex items-center gap-3 text-white mb-4">
                <Phone />
                <div>
                  <h1 className="text-xl sm:text-3xl font-bold">SCCP Configurator</h1>
                  <p className="text-blue-100 text-xs sm:text-sm hidden sm:block">Cisco SCCP phones management for Asterisk</p>
                </div>
              </div>
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2 sm:gap-3">
                  <button
                    onClick={() => setShowXMLSettings(true)}
                    className="bg-purple-500 text-white px-3 py-2 rounded-lg hover:bg-purple-600 transition font-semibold flex items-center gap-2"
                    title="Generate XML configuration files"
                  >
                    <FileText /> <span className="hidden sm:inline">XML Config</span>
                  </button>
                  <button
                    onClick={manualReload}
                    className="bg-white/10 text-white px-3 py-2 rounded-lg hover:bg-white/20 transition font-semibold flex items-center gap-2"
                    title="Run SCCP & Dialplan reload"
                  >
                    <Refresh /> <span className="hidden sm:inline">Reload</span>
                  </button>
                  <button
                    onClick={() => loadStatus(true)}
                    disabled={refreshing}
                    className={`bg-white text-blue-700 px-3 py-2 rounded-lg hover:bg-blue-50 transition font-semibold flex items-center gap-2 ${refreshing ? 'opacity-60 animate-pulse' : ''}`}
                    title="Refresh Status"
                  >
                    <Refresh /> <span className="hidden sm:inline">{refreshing ? 'Refreshing…' : 'Refresh Status'}</span>
                  </button>
                  <button
                    onClick={saveConfig}
                    disabled={saving || !lockGranted}
                    title={!lockGranted ? ('Locked by ' + (lockHolder || 'someone else') + ' - wait and refresh (Ctrl+F5)') : 'Save & Apply'}
                    className={`${lockGranted ? 'bg-green-500 hover:bg-green-600' : 'bg-gray-400 cursor-not-allowed'} text-white px-3 py-2 rounded-lg transition font-semibold flex items-center gap-2 ${saving ? 'opacity-60 animate-pulse' : ''}`}
                  >
                    <Save /> <span className="hidden sm:inline">{!lockGranted ? ('🔒 ' + (lockHolder ? ('Locked by ' + lockHolder) : 'Locked')) : (saving ? 'Saving…' : 'Save & Apply')}</span>
                  </button>
                  {!lockGranted && (
                    <button
                      onClick={requestAccess}
                      disabled={requestSent}
                      title="Ask the current editor to release the lock"
                      className={`px-3 py-2 rounded-lg font-semibold text-white flex items-center gap-2 ${requestSent ? 'bg-gray-400 cursor-default' : 'bg-amber-500 hover:bg-amber-600'}`}
                    >
                      {requestSent ? '⏳ Requested…' : '✋ Request access'}
                    </button>
                  )}
                  {lockGranted && lockRequest && (
                    <button
                      onClick={handoffLock}
                      title={lockRequest + ' is waiting to edit'}
                      className="px-3 py-2 rounded-lg font-semibold text-white bg-orange-500 hover:bg-orange-600 flex items-center gap-2 animate-pulse"
                    >
                      🔔 {lockRequest} wants access — Release
                    </button>
                  )}
                </div>
                <div className="flex items-center gap-2 ml-auto">
                  <div className="hidden sm:flex items-center gap-2 bg-white/10 px-3 py-2 rounded-lg text-white">
                    <UserIcon />
                    <div>
                      <div className="text-sm font-semibold">{currentUser?.name || 'User'}</div>
                      <div className="text-xs text-blue-100">@{currentUser?.username || 'unknown'}</div>
                    </div>
                  </div>
                  <button
                    onClick={() => setShowLogoutConfirm(true)}
                    className="bg-red-500 text-white p-2 rounded-lg hover:bg-red-600 transition font-semibold"
                    title="Log Out"
                  >
                    <LogOut />
                  </button>
                </div>
              </div>
            </div>

            {/* --- TAB NAVIGATION --- */}
            <div className="flex gap-2 mb-4">
              <button
                onClick={() => setCurrentView('phones')}
                className={`flex-1 sm:flex-none px-5 py-2 rounded-lg font-semibold flex items-center justify-center gap-2 transition ${currentView === 'phones' ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-blue-50'}`}
              >
                <PhoneOutgoing /> Phones
              </button>
              <button
                onClick={handleSwitchToDirectory}
                className={`flex-1 sm:flex-none px-5 py-2 rounded-lg font-semibold flex items-center justify-center gap-2 transition ${currentView === 'directory' ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-blue-50'}`}
              >
                <Book /> Directory
              </button>
              <button
                onClick={() => { setCurrentView('log'); loadAuditLog(); }}
                className={`flex-1 sm:flex-none px-5 py-2 rounded-lg font-semibold flex items-center justify-center gap-2 transition ${currentView === 'log' ? 'bg-blue-600 text-white shadow' : 'bg-white text-gray-700 hover:bg-blue-50'}`}
              >
                <ClipboardList /> Log
              </button>
            </div>

            {/* --- NEW UNIFIED TABLE --- */}
            {currentView === 'phones' && <div className="bg-white rounded-lg shadow-2xl p-4 sm:p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg sm:text-2xl font-bold text-gray-800 flex items-center gap-2">
                  <PhoneOutgoing /> Provisioned Phones
                </h2>
                <button
                  onClick={handleOpenAddModal}
                  className="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2 text-sm sm:text-base"
                >
                  <Plus /> <span className="hidden sm:inline">Add Phone</span><span className="sm:hidden">Add</span>
                </button>
              </div>

              <div className="mb-4 flex gap-2">
                <input
                  type="text"
                  placeholder="Search by person, extension, description or department…"
                  value={phoneSearch}
                  onChange={e => setPhoneSearch(e.target.value)}
                  className="flex-1 px-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                />
                <select
                  value={sortBy}
                  onChange={e => setSortBy(e.target.value)}
                  className="px-3 py-2 border rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-300"
                  title="Sort order"
                >
                  <option value="name">Sort: Name (A–Z)</option>
                  <option value="extension">Sort: Extension</option>
                </select>
              </div>

              {/* Mobile card view */}
              {(() => {
                const filteredPhones = provisionedPhones.filter(phone => {
                  const q = phoneSearch.toLowerCase();
                  return !q
                    || (phone.label       || '').toLowerCase().includes(q)
                    || (phone.extension   || '').toLowerCase().includes(q)
                    || (phone.description || '').toLowerCase().includes(q)
                    || (phone.department  || '').toLowerCase().includes(q)
                    || (phone.mac         || '').toLowerCase().includes(q);
                });
                filteredPhones.sort((a, b) => {
                  if (sortBy === 'extension') return (parseInt(a.extension, 10) || 0) - (parseInt(b.extension, 10) || 0);
                  return (a.label || '').localeCompare(b.label || '', undefined, { sensitivity: 'base' });
                });
                return (
                  <>
                    <div className="sm:hidden space-y-3">
                      {filteredPhones.map((phone) => (
                        <div key={phone.mac} className="border rounded-lg p-3 bg-gray-50">
                          <div className="flex justify-between items-start mb-2">
                            <div>
                              <div className="font-semibold text-gray-800">{phone.label || <span className="text-gray-400 italic">—</span>}</div>
                              {phone.description && <div className="text-xs text-gray-400">{phone.description}</div>}
                              {phone.department && <div className="text-xs text-blue-500">{phone.department}</div>}
                            </div>
                            <div className="text-blue-700 font-bold text-lg">{phone.extension}</div>
                          </div>
                          <div className="text-xs font-mono text-gray-500 mb-2">{phone.mac} · Cisco {phone.model}</div>
                          <div className="flex items-center justify-between">
                            <div className="flex gap-2">
                              <StatusLabel status={deviceStatus[phone.mac]} />
                              <StatusLabel status={lineStatus[phone.extension]} />
                            </div>
                            <div className="flex gap-1">
                              <button onClick={() => setInfoPhone(phone)} className="text-gray-500 hover:text-gray-700 p-1.5" title="Info"><Info /></button>
                              <button onClick={() => handleOpenEditModal(phone)} className="text-blue-600 hover:text-blue-800 p-1.5" title="Edit"><Edit /></button>
                              <button onClick={() => setShowDeleteConfirm(phone.mac)} className="text-red-600 hover:text-red-800 p-1.5" title="Delete"><Trash /></button>
                            </div>
                          </div>
                        </div>
                      ))}
                      {provisionedPhones.length === 0 && (
                        <div className="text-center text-gray-500 py-10">No phones provisioned yet.</div>
                      )}
                    </div>

                    {/* Desktop table view */}
                    <div className="hidden sm:block overflow-x-auto">
                      <table className="w-full min-w-full text-left">
                        <thead className="border-b bg-gray-50">
                          <tr>
                            <th className="px-4 py-3 text-sm font-semibold text-gray-600">Device (MAC / Model)</th>
                            <th className="px-4 py-3 text-sm font-semibold text-gray-600">Line</th>
                            <th className="px-4 py-3 text-sm font-semibold text-gray-600">Person / Description</th>
                            <th className="px-4 py-3 text-sm font-semibold text-gray-600">Device Status</th>
                            <th className="px-4 py-3 text-sm font-semibold text-gray-600">Line Status</th>
                            <th className="px-4 py-3 text-sm font-semibold text-gray-600 text-right">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          {filteredPhones.map((phone) => (
                      <tr key={phone.mac} className="border-b hover:bg-gray-50">
                        <td className="px-4 py-4">
                          <div className="font-mono font-semibold text-gray-900">{phone.mac}</div>
                          <div className="text-sm text-gray-600">Cisco {phone.model}</div>
                        </td>
                        <td className="px-4 py-4">
                          <div className="font-semibold text-blue-700 text-base">{phone.extension}</div>
                        </td>
                        <td className="px-4 py-4">
                          <div className="font-semibold text-gray-800">{phone.label || <span className="text-gray-300 italic">—</span>}</div>
                          {phone.description && <div className="text-sm text-gray-400">{phone.description}</div>}
                          {phone.department && <div className="text-xs text-blue-500">{phone.department}</div>}
                        </td>
                        <td className="px-4 py-4">
                          <StatusLabel status={deviceStatus[phone.mac]} />
                        </td>
                        <td className="px-4 py-4">
                          <StatusLabel status={lineStatus[phone.extension]} />
                        </td>
                        <td className="px-4 py-4 text-right">
                           <button onClick={() => setInfoPhone(phone)} className="text-gray-500 hover:text-gray-700 p-2" title="Info"><Info /></button>
                           <button onClick={() => handleOpenEditModal(phone)} className="text-blue-600 hover:text-blue-800 p-2" title="Edit"><Edit /></button>
                           <button onClick={() => setShowDeleteConfirm(phone.mac)} className="text-red-600 hover:text-red-800 p-2" title="Delete"><Trash /></button>
                        </td>
                      </tr>
                          ))}
                        </tbody>
                      </table>
                      {provisionedPhones.length === 0 && (
                        <div className="text-center text-gray-500 py-10">
                          No phones provisioned yet. Click "Add Phone" to get started.
                        </div>
                      )}
                    </div>
                  </>
                );
              })()}
            </div>}


            {/* --- DIRECTORY VIEW --- */}
            {currentView === 'directory' && (
              <div className="bg-white rounded-lg shadow-2xl p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                  <h2 className="text-lg sm:text-2xl font-bold text-gray-800 flex items-center gap-2">
                    <Book /> Phone Directory
                  </h2>
                  <div className="flex flex-wrap gap-2">
                    <button
                      onClick={() => {
                        if (contacts.length === 0) { showToast('No contacts to export', 'info'); return; }
                        const rows = [['Person / Description','Number','Department'], ...contacts.map(c => [c.name, c.number, c.department || ''])];
                        const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
                        const a = document.createElement('a');
                        a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
                        a.download = 'directory.csv';
                        a.click();
                      }}
                      className="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 transition flex items-center gap-2 font-semibold text-sm"
                      title="Export directory as CSV"
                    >
                      <Download /> <span className="hidden sm:inline">Export CSV</span>
                    </button>
                    <button
                      onClick={() => {
                        const existing = new Set(contacts.map(c => c.number));
                        const toImport = provisionedPhones
                          .filter(p => p.extension && !existing.has(p.extension))
                          .map(p => ({ name: p.description || p.label || p.extension, number: p.extension, department: p.department || '' }));
                        if (toImport.length === 0) { showToast('All lines already in directory', 'info'); return; }
                        saveContacts([...contacts, ...toImport]);
                      }}
                      disabled={contactSaving}
                      className="bg-gray-100 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-200 transition flex items-center gap-2 font-semibold text-sm"
                      title="Add all configured phone lines to the directory"
                    >
                      <PhoneOutgoing /> <span className="hidden sm:inline">Import Lines</span>
                    </button>
                    <button
                      onClick={() => { setEditContact(null); setShowContactModal(true); }}
                      className="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2 text-sm"
                    >
                      <Plus /> <span className="hidden sm:inline">Add Contact</span>
                    </button>
                  </div>
                </div>

                <div className="mb-4">
                  <input
                    type="text"
                    placeholder="Search by name, number or department…"
                    value={contactSearch}
                    onChange={e => setContactSearch(e.target.value)}
                    className="w-full px-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-300"
                  />
                </div>

                {/* Mobile card view */}
                {(() => {
                  const filtered = contacts
                    .map((c, i) => ({...c, _idx: i}))
                    .filter(c => {
                      const q = contactSearch.toLowerCase();
                      return !q
                        || (c.name       || '').toLowerCase().includes(q)
                        || (c.number     || '').toLowerCase().includes(q)
                        || (c.department || '').toLowerCase().includes(q);
                    });
                  return (
                    <>
                      <div className="sm:hidden space-y-3">
                        {filtered.map(c => (
                          <div key={c._idx} className="border rounded-lg p-3 bg-gray-50">
                            <div className="flex justify-between items-start">
                              <div>
                                <div className="font-semibold text-gray-900">{c.name}</div>
                                {c.department && <div className="text-xs text-gray-500">{c.department}</div>}
                              </div>
                              <div className="font-mono font-bold text-blue-700">{c.number}</div>
                            </div>
                            <div className="flex justify-end gap-1 mt-2">
                              <button onClick={() => setInfoContact(c)} className="text-gray-500 hover:text-gray-700 p-1.5" title="Info"><Info /></button>
                              <button onClick={() => { setEditContact(c); setShowContactModal(true); }} className="text-blue-600 hover:text-blue-800 p-1.5" title="Edit"><Edit /></button>
                              <button onClick={() => setShowDeleteContactConfirm(c._idx)} className="text-red-600 hover:text-red-800 p-1.5" title="Delete"><Trash /></button>
                            </div>
                          </div>
                        ))}
                        {contacts.length === 0 && <div className="text-center text-gray-500 py-10">No contacts yet.</div>}
                      </div>

                      {/* Desktop table view */}
                      <div className="hidden sm:block overflow-x-auto">
                        <table className="w-full text-left">
                          <thead className="border-b bg-gray-50">
                            <tr>
                              <th className="px-4 py-3 text-sm font-semibold text-gray-600">Name</th>
                              <th className="px-4 py-3 text-sm font-semibold text-gray-600">Number</th>
                              <th className="px-4 py-3 text-sm font-semibold text-gray-600">Department</th>
                              <th className="px-4 py-3 text-sm font-semibold text-gray-600 text-right">Actions</th>
                            </tr>
                          </thead>
                          <tbody>
                            {filtered.map(c => (
                              <tr key={c._idx} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-3 font-semibold text-gray-900">{c.name}</td>
                                <td className="px-4 py-3 font-mono text-blue-700">{c.number}</td>
                                <td className="px-4 py-3 text-gray-600">{c.department || <span className="text-gray-300 italic">—</span>}</td>
                                <td className="px-4 py-3 text-right">
                                  <button onClick={() => setInfoContact(c)} className="text-gray-500 hover:text-gray-700 p-2" title="Info"><Info /></button>
                                  <button onClick={() => { setEditContact(c); setShowContactModal(true); }} className="text-blue-600 hover:text-blue-800 p-2" title="Edit"><Edit /></button>
                                  <button onClick={() => setShowDeleteContactConfirm(c._idx)} className="text-red-600 hover:text-red-800 p-2" title="Delete"><Trash /></button>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                        {contacts.length === 0 && (
                          <div className="text-center text-gray-500 py-10">
                            No contacts yet. Click "Add Contact" to create the directory.
                          </div>
                        )}
                      </div>
                    </>
                  );
                })()}

                <div className="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-700">
                  <strong>Phone access:</strong> Phones access the directory via the <code>Directories</code> button using the URL embedded in their XML config file. Regenerate XML files after adding contacts to ensure phones have the latest URL.
                </div>
              </div>
            )}


            {currentView === 'log' && (
              <div className="bg-white rounded-lg shadow-2xl p-4 sm:p-6">
                <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                  <div>
                    <h2 className="text-lg sm:text-2xl font-bold text-gray-800 flex items-center gap-2">
                      <ClipboardList /> Audit Log
                    </h2>
                    <div className="flex items-center gap-1.5 mt-1 text-xs text-green-700 font-medium">
                      <Lock />
                      <span>Append-only — entries cannot be deleted or modified</span>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <a
                      href="api.php?action=export_log"
                      className="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2 text-sm font-semibold"
                      title="Download all log entries as CSV"
                    >
                      <Download /> Export CSV
                    </a>
                    <button
                      onClick={loadAuditLog}
                      disabled={logLoading}
                      className="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition flex items-center gap-2 text-sm disabled:opacity-60"
                    >
                      <Refresh /> {logLoading ? 'Loading…' : 'Refresh'}
                    </button>
                  </div>
                </div>
                {auditLog.length === 0 && !logLoading && (
                  <div className="text-center text-gray-400 py-12">No log entries yet.</div>
                )}
                {auditLog.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                      <thead className="border-b bg-gray-50">
                        <tr>
                          <th className="px-3 py-2 font-semibold text-gray-600 whitespace-nowrap">Time</th>
                          <th className="px-3 py-2 font-semibold text-gray-600">User</th>
                          <th className="px-3 py-2 font-semibold text-gray-600">Action</th>
                          <th className="px-3 py-2 font-semibold text-gray-600">ID</th>
                          <th className="px-3 py-2 font-semibold text-gray-600">Description</th>
                          <th className="px-3 py-2 font-semibold text-gray-600">IP</th>
                        </tr>
                      </thead>
                      <tbody>
                        {auditLog.map(entry => {
                          const actionColor =
                            entry.action.includes('add')    ? 'bg-green-100 text-green-700'  :
                            entry.action.includes('remove') ? 'bg-red-100 text-red-700'     :
                            entry.action.includes('modify') ? 'bg-yellow-100 text-yellow-700' :
                            'bg-gray-100 text-gray-600';
                          return (
                            <tr key={entry.id} className="border-b hover:bg-gray-50">
                              <td className="px-3 py-2 text-gray-500 whitespace-nowrap font-mono text-xs">{entry.created_at}</td>
                              <td className="px-3 py-2 font-semibold">{entry.username}</td>
                              <td className="px-3 py-2">
                                <span className={`px-2 py-0.5 rounded text-xs font-semibold ${actionColor}`}>
                                  {entry.action}
                                </span>
                              </td>
                              <td className="px-3 py-2 font-mono text-blue-700 whitespace-nowrap">{entry.entity_id || '—'}</td>
                              <td className="px-3 py-2 text-gray-700">{entry.description}</td>
                              <td className="px-3 py-2 text-gray-400 font-mono text-xs">{entry.ip_address}</td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                    <div className="mt-3 text-xs text-gray-400 text-right">{auditLog.length} entries — all records permanently stored</div>
                  </div>
                )}
              </div>
            )}
            {/* Contact Add/Edit Modal */}
            {showContactModal && (
              <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
                <div className="bg-white rounded-lg p-6 max-w-md w-full">
                  <h3 className="text-xl font-bold mb-4">{editContact ? 'Edit Contact' : 'Add Contact'}</h3>
                  <form onSubmit={e => {
                    e.preventDefault();
                    const fd = new FormData(e.target);
                    handleSaveContact({ name: fd.get('name').trim(), number: fd.get('number').trim(), department: fd.get('department').trim() });
                  }}>
                    <div className="space-y-4">
                      <div>
                        <label className="block text-sm font-semibold mb-1">Name *</label>
                        <input name="name" required defaultValue={editContact?.name || ''} className="w-full px-3 py-2 border rounded-lg" placeholder="John Doe" />
                      </div>
                      <div>
                        <label className="block text-sm font-semibold mb-1">Extension / Number *</label>
                        <input name="number" required defaultValue={editContact?.number || ''} className="w-full px-3 py-2 border rounded-lg font-mono" placeholder="502" />
                      </div>
                      <div>
                        <label className="block text-sm font-semibold mb-1">Department <span className="text-gray-400 font-normal">(optional)</span></label>
                        <input name="department" defaultValue={editContact?.department || ''} className="w-full px-3 py-2 border rounded-lg" placeholder="IT, Reception…" />
                      </div>
                    </div>
                    <div className="flex gap-3 mt-6">
                      <button type="button" onClick={() => { setShowContactModal(false); setEditContact(null); }} className="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-semibold">Cancel</button>
                      <button type="submit" disabled={contactSaving} className="flex-1 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold disabled:opacity-60">
                        {contactSaving ? 'Saving…' : (editContact ? 'Save Changes' : 'Add Contact')}
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            )}

            {/* Phone Info Modal */}
            {infoPhone && (
              <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50" onClick={() => setInfoPhone(null)}>
                <div className="bg-white rounded-lg p-6 max-w-md w-full" onClick={e => e.stopPropagation()}>
                  <div className="flex items-center justify-between mb-5">
                    <h3 className="text-xl font-bold flex items-center gap-2"><Info /> Phone Info</h3>
                    <button onClick={() => setInfoPhone(null)} className="text-gray-400 hover:text-gray-600"><XCircle /></button>
                  </div>
                  <div className="space-y-3 text-sm">
                    {[
                      ['MAC Address',        infoPhone.mac],
                      ['MAC Address 2',      infoPhone.mac2 || '—'],
                      ['Model',              `Cisco ${infoPhone.model}`],
                      ['Extension',          infoPhone.extension],
                      ['Caller ID (Label)',   infoPhone.label],
                      ['Person / Description',infoPhone.description || '—'],
                      ['Department',         infoPhone.department || '—'],
                      ['PIN',                infoPhone.pin],
                      ['Context',            infoPhone.context],
                      ['Forward',            infoPhone.fwd_number ? (infoPhone.fwd_number + ' (after ' + (infoPhone.fwd_timeout||'30') + 's)') : '—'],
                      ['Device Status',      deviceStatus[infoPhone.mac] || 'unknown'],
                      ['Line Status',        lineStatus[infoPhone.extension] || 'unknown'],
                    ].map(([label, value]) => (
                      <div key={label} className="flex justify-between border-b pb-2 last:border-0">
                        <span className="text-gray-500 font-medium">{label}</span>
                        <span className="font-semibold text-gray-800 text-right font-mono">{value}</span>
                      </div>
                    ))}
                  </div>
                  <button onClick={() => setInfoPhone(null)} className="mt-5 w-full bg-gray-100 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-200 font-semibold">Close</button>
                </div>
              </div>
            )}

            {/* Contact Info Modal */}
            {infoContact && (
              <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50" onClick={() => setInfoContact(null)}>
                <div className="bg-white rounded-lg p-6 max-w-md w-full" onClick={e => e.stopPropagation()}>
                  <div className="flex items-center justify-between mb-5">
                    <h3 className="text-xl font-bold flex items-center gap-2"><Info /> Contact Info</h3>
                    <button onClick={() => setInfoContact(null)} className="text-gray-400 hover:text-gray-600"><XCircle /></button>
                  </div>
                  <div className="space-y-3 text-sm">
                    {[
                      ['Person / Description', infoContact.name],
                      ['Number',               infoContact.number],
                      ['Department',           infoContact.department || '—'],
                    ].map(([label, value]) => (
                      <div key={label} className="flex justify-between border-b pb-2 last:border-0">
                        <span className="text-gray-500 font-medium">{label}</span>
                        <span className="font-semibold text-gray-800 text-right font-mono">{value}</span>
                      </div>
                    ))}
                  </div>
                  <button onClick={() => setInfoContact(null)} className="mt-5 w-full bg-gray-100 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-200 font-semibold">Close</button>
                </div>
              </div>
            )}

            {/* Delete Contact Confirm */}
            <ConfirmModal
              show={showDeleteContactConfirm !== null}
              title="Delete Contact"
              message={`Delete "${contacts[showDeleteContactConfirm]?.name}"?`}
              confirmText="Delete"
              cancelText="Cancel"
              onConfirm={() => handleDeleteContact(showDeleteContactConfirm)}
              onCancel={() => setShowDeleteContactConfirm(null)}
            />

            {/* --- OTHER MODALS (Unchanged) --- */}
            
            {/* XML Settings Modal */}
            {showXMLSettings && (
              <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
                <div className="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                  <div className="flex items-center justify-between mb-4">
                    <h3 className="text-xl font-bold flex items-center gap-2">
                      <Settings /> XML Configuration Settings
                    </h3>
                  </div>

                  <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                    <div className="flex items-start gap-2">
                      <AlertTriangle />
                      <div className="text-sm">
                        <p className="font-semibold text-yellow-800">Important Notes:</p>
                        <ul className="list-disc ml-4 mt-2 text-yellow-700">
                          <li><strong>Cisco 7940, 7960, 7970, 7975, 8941, 8945</strong> - Full XML support</li>
                          <li><strong>Cisco 7912, 7906, 7911</strong> - Limited/No XML support (basic display only)</li>
                          <li>XML files will be generated in <code>/var/lib/tftpboot/</code> (or your server's TFTP root)</li>
                          <li>Phones must reboot to load new XML configuration</li>
                        </ul>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-semibold mb-1">Time Format</label>
                        <select
                          value={xmlSettings.timeFormat}
                          onChange={(e) => setXmlSettings({...xmlSettings, timeFormat: e.target.value})}
                          className="w-full px-3 py-2 border rounded-lg"
                        >
                          <option value="12">12-hour (AM/PM)</option>
                          <option value="24">24-hour</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-sm font-semibold mb-1">Date Format</label>
                        <select
                          value={xmlSettings.dateFormat}
                          onChange={(e) => setXmlSettings({...xmlSettings, dateFormat: e.target.value})}
                          className="w-full px-3 py-2 border rounded-lg"
                        >
                          <option value="D.M.Y">DD.MM.YYYY</option>
                          <option value="M/D/Y">MM/DD/YYYY</option>
                          <option value="Y-M-D">YYYY-MM-DD</option>
                        </select>
                      </div>
                    </div>

                    <div>
                      <label className="flex items-center gap-2 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={xmlSettings.enableDirectory}
                          onChange={(e) => setXmlSettings({...xmlSettings, enableDirectory: e.target.checked})}
                          className="w-4 h-4"
                        />
                        <span className="text-sm font-semibold">Enable Directory (Phone Book)</span>
                      </label>
                      <p className="text-xs text-gray-500 mt-1 ml-6">
                        Enables access to company directory on supported phones
                      </p>
                    </div>

                    <div className="border-t pt-4">
                      <h4 className="font-semibold mb-2">Device Summary</h4>
                      <div className="space-y-2 text-sm">
                        <div className="flex justify-between">
                          <span>Total Devices:</span>
                          <span className="font-semibold">{provisionedPhones.length}</span>
                        </div>
                        <div className="flex justify-between">
                          <span>Full XML Support:</span>
                          <span className="font-semibold text-green-600">
                            {provisionedPhones.filter(d => xmlSupportedModels.includes(d.model)).length}
                          </span>
                        </div>
                        <div className="flex justify-between">
                          <span>Limited/No XML Support:</span>
                          <span className="font-semibold text-yellow-600">
                            {provisionedPhones.filter(d => xmlLimitedModels.includes(d.model)).length}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex gap-2 mt-6">
                    <button
                      onClick={generateXML}
                      disabled={saving}
                      className="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 disabled:opacity-50 font-semibold"
                    >
                      {saving ? 'Generating...' : 'Generate XML Files'}
                    </button>
                    <button
                      onClick={() => setShowXMLSettings(false)}
                      className="flex-1 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-semibold"
                    >
                      Cancel
                    </button>
                  </div>
                </div>
              </div>
            )}

            {/* XML Results Modal */}
            {showXMLResults && xmlResults && (
              <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
                <div className="bg-white rounded-lg p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                  <h3 className="text-xl font-bold mb-4 flex items-center gap-2">
                    {xmlResults.success ? <CheckCircle /> : <XCircle />}
                    XML Generation Results
                  </h3>

                  {xmlResults.warnings && xmlResults.warnings.length > 0 && (
                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                      <div className="flex items-start gap-2">
                        <AlertTriangle />
                        <div className="flex-1">
                          <p className="font-semibold text-yellow-800 mb-2">Warnings:</p>
                          <ul className="text-sm text-yellow-700 space-y-1">
                            {xmlResults.warnings.map((warning, idx) => (
                              <li key={idx}>• {warning}</li>
                            ))}
                          </ul>
                        </div>
                      </div>
                    </div>
                  )}

                  <div className="bg-gray-50 rounded-lg p-4 mb-4">
                    <h4 className="font-semibold mb-3">Generated Files:</h4>
                    <div className="space-y-2 max-h-60 overflow-y-auto">
                      {xmlResults.results && Object.entries(xmlResults.results).map(([file, status]) => (
                        <div key={file} className="flex items-center justify-between text-sm py-2 border-b border-gray-200 last:border-0">
                          <span className="font-mono">{file}.cnf.xml</span>
                          <span className={`px-3 py-1 rounded-full text-xs font-semibold ${
                            status === 'success' ? 'bg-green-100 text-green-700' :
                            status === 'skipped' ? 'bg-yellow-100 text-yellow-700' :
                            'bg-red-100 text-red-700'
                          }`}>
                            {status}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>

                  <button
                    onClick={() => setShowXMLResults(false)}
                    className="w-full bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300 font-semibold"
                  >
                    Close
                  </button>
                </div>
              </div>
            )}
            
            {/* Reload Results Modal */}
            {showReloadDialog && reloadResult && (
              <div className="fixed inset-0 bg-black/60 flex items-center justify-center p-4 z-50">
                <div className="bg-white rounded-lg p-6 max-w-lg w-full">
                  <h3 className="text-xl font-bold mb-4">Reload Results</h3>
                  <div className="space-y-4">
                    <div className="bg-gray-100 p-4 rounded-lg">
                      <h4 className="font-semibold text-gray-800">SCCP Reload</h4>
                      <pre className="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{reloadResult.output || reloadResult.message || "No output"}</pre>
                    </div>
                    <div className="bg-gray-100 p-4 rounded-lg">
                      <h4 className="font-semibold text-gray-800">Dialplan Reload</h4>
                      <pre className="text-sm text-gray-600 mt-2 whitespace-pre-wrap">{reloadResult.dialplan_output || "No output"}</pre>
                    </div>
                  </div>
                  <button
                    onClick={() => setShowReloadDialog(false)}
                    className="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 font-semibold mt-6"
                  >
                    Close
                  </button>
                </div>
              </div>
            )}

          </div>
        </div>
      );
    }

    ReactDOM.render(<SCCPConfigurator />, document.getElementById('root'));

  </script>
</body>
</html>
