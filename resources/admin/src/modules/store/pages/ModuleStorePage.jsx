import { Suspense, lazy, useEffect, useMemo, useRef, useState } from 'react';
import ArrowLeftOutlined from '@ant-design/icons/ArrowLeftOutlined';
import Button from 'antd/es/button';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Modal from 'antd/es/modal';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;
const ModuleStoreTable = lazy(() => import('../components/ModuleStoreTable'));
const ModuleLifecycleActionPanel = lazy(() => import('../components/ModuleLifecycleActionPanel'));
const ModuleUpgradeChangelogModal = lazy(() => import('../components/ModuleUpgradeChangelogModal'));

export default function ModuleStorePage({ modules, onAction, permissions }) {
    const [pendingAction, setPendingAction] = useState(null);
    const actionLock = useRef(false);
    const handleAction = async (...args) => {
        if (actionLock.current) return false;
        actionLock.current = true;
        setPendingAction(args[1]);
        try {
            return await onAction?.(...args);
        } finally {
            actionLock.current = false;
            setPendingAction(null);
        }
    };
    const canUpgrade = permissions?.upgrade ?? false;
    const [modulePath, setModulePath] = useState([]);
    const selectedModuleKey = modulePath[0] ?? null;
    const relatedModuleKey = modulePath.length > 1 ? modulePath.at(-1) : null;
    const [changelogModuleKey, setChangelogModuleKey] = useState(null);

    useEffect(() => {
        setModulePath((path) => {
            const availablePath = path.filter((key) => modules?.some((moduleCard) => moduleCard.key === key));
            return availablePath.length === path.length ? path : availablePath;
        });
    }, [modules]);

    const selectedModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === selectedModuleKey) ?? null, [modules, selectedModuleKey]);
    const relatedModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === relatedModuleKey) ?? null, [modules, relatedModuleKey]);
    const changelogModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === changelogModuleKey) ?? null, [modules, changelogModuleKey]);
    const previousModule = modules.find((moduleCard) => moduleCard.key === modulePath.at(-2));
    const openRelatedModule = (moduleCard) => {
        if (actionLock.current) return;
        setModulePath((path) => {
            const existingIndex = path.indexOf(moduleCard.key);
            return existingIndex < 0 ? [...path, moduleCard.key] : path.slice(0, existingIndex + 1);
        });
    };

    return (
        <Card title="Quản lý App">
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">VÒNG ĐỜI APP</Text>
                <Paragraph style={{ marginBottom: 0 }}>
                    Quản lý danh sách App, trạng thái cài đặt, kích hoạt, nâng cấp và nhật ký thay đổi ngay tại một nơi.
                </Paragraph>
            </Space>
            <Suspense fallback={<Card loading title="Danh sách App" />}>
                <ModuleStoreTable
                    modules={modules}
                    onOpenDetails={(moduleCard) => setModulePath([moduleCard.key])}
                    onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                />
            </Suspense>

            <Drawer
                open={Boolean(selectedModuleKey)}
                onClose={() => { if (!actionLock.current && !relatedModuleKey && !changelogModuleKey) setModulePath([]); }}
                closable={!pendingAction}
                maskClosable={!pendingAction && !relatedModuleKey && !changelogModuleKey}
                keyboard={!pendingAction && !relatedModuleKey && !changelogModuleKey}
                width="min(760px, 96vw)"
                title={selectedModule ? `Chi tiết App: ${selectedModule.name}` : 'Chi tiết App'}
                className="module-detail-drawer"
                destroyOnHidden
            >
                {selectedModule ? (
                    <Suspense fallback={<Card loading title="Quản lý App" />}>
                        <ModuleLifecycleActionPanel
                            key={selectedModule.key}
                            moduleCard={selectedModule}
                            modules={modules}
                            onOpenModule={openRelatedModule}
                            permissions={permissions}
                            onAction={handleAction}
                            pendingAction={pendingAction}
                            onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                        />
                    </Suspense>
                ) : null}
            </Drawer>

            <Modal
                open={Boolean(relatedModuleKey)}
                title={(
                    <div className="module-detail-modal-title">
                        <span>{relatedModule ? `Chi tiết App: ${relatedModule.name}` : 'Chi tiết App'}</span>
                        {previousModule ? (
                            <Button
                                icon={<ArrowLeftOutlined />}
                                disabled={Boolean(pendingAction)}
                                onClick={() => { if (!actionLock.current) setModulePath((path) => path.slice(0, -1)); }}
                                title={`Quay lại ${previousModule.name}`}
                            >
                                Quay lại
                            </Button>
                        ) : null}
                    </div>
                )}
                onCancel={() => { if (!actionLock.current) setModulePath((path) => path.slice(0, 1)); }}
                closable={!pendingAction}
                maskClosable={!pendingAction}
                keyboard={!pendingAction && !changelogModuleKey}
                footer={null}
                width="min(900px, 96vw)"
                zIndex={1100}
                centered
                className="module-detail-modal"
                destroyOnHidden
            >
                {relatedModule ? (
                    <Suspense fallback={<Card loading title="Quản lý App" />}>
                        <ModuleLifecycleActionPanel
                            key={relatedModule.key}
                            moduleCard={relatedModule}
                            modules={modules}
                            onOpenModule={openRelatedModule}
                            permissions={permissions}
                            onAction={handleAction}
                            pendingAction={pendingAction}
                            onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                        />
                    </Suspense>
                ) : null}
            </Modal>

            {changelogModuleKey ? (
                <Suspense fallback={null}>
                    <ModuleUpgradeChangelogModal
                        open={Boolean(changelogModuleKey)}
                        zIndex={1200}
                        moduleCard={changelogModule}
                        canUpgrade={canUpgrade}
                        onCancel={() => setChangelogModuleKey(null)}
                        onAction={async (...args) => {
                            const saved = await handleAction(...args);
                            if (saved !== false) setChangelogModuleKey(null);
                        }}
                    />
                </Suspense>
            ) : null}
        </Card>
    );
}
