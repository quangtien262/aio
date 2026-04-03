import Alert from 'antd/es/alert';
import Card from 'antd/es/card';
import { useLocation, useNavigate } from 'react-router-dom';
import SetupWizardPage from '../../modules/setup/pages/SetupWizardPage';
import useAdminRouteResource from '../../shared/hooks/useAdminRouteResource';

export default function SetupRoutePage({ canAccess, canComplete, callAdminApi, runAdminAction }) {
    const location = useLocation();
    const navigate = useNavigate();
    const { data, loading, error, reload } = useAdminRouteResource({
        enabled: canAccess,
        loader: async () => {
            const payload = await callAdminApi('/admin/api/setup');

            return payload.data ?? null;
        },
    });

    const pushSetupStepFeedback = (stepKey) => {
        const nextParams = new URLSearchParams(location.search);
        nextParams.set('focusStep', stepKey);
        nextParams.set('completedStep', stepKey);

        navigate({ pathname: location.pathname, search: `?${nextParams.toString()}` }, { replace: true });
    };

    if (loading) {
        return <Card loading title="Setup" />;
    }

    if (error) {
        return <Alert type="error" showIcon message={error} />;
    }

    return (
        <SetupWizardPage
            setup={data}
            onSaveProfile={async (payload) => {
                const didSave = await runAdminAction(
                    () => callAdminApi('/admin/api/setup', { method: 'PUT', body: JSON.stringify(payload) }),
                    'Đã lưu cấu hình setup.',
                    reload,
                );

                if (didSave) {
                    pushSetupStepFeedback('branding');
                }
            }}
            onCompleteStep={async (stepKey) => {
                const didComplete = await runAdminAction(
                    () => callAdminApi(`/admin/api/setup/steps/${stepKey}`, { method: 'POST' }),
                    'Đã cập nhật bước setup.',
                    reload,
                );

                if (didComplete && stepKey === 'finish') {
                    pushSetupStepFeedback('finish');
                }
            }}
            canEditProfile={canComplete}
            canCompleteSteps={canComplete}
        />
    );
}
