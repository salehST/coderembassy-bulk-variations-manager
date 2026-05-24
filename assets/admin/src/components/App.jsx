/**
 * Root app shell.
 */
import Router from './Router';
import Sidebar from './Sidebar';
import Topbar from './Topbar';
import ModalHost from './ModalHost';
import ToastProvider from './shared/ToastProvider';
import { useJobsPolling } from '../hooks/useJobsPolling';
import { useShortcut } from '../hooks/useShortcut';

export default function App() {
	useJobsPolling();
	useShortcut();

	return (
		<ToastProvider>
			<div className="bv-app">
				<div className="bv-layout">
					<Sidebar />
					<div className="bv-main">
						<Topbar />
						<div className="bv-content">
							<Router />
						</div>
					</div>
				</div>
				<ModalHost />
			</div>
		</ToastProvider>
	);
}
