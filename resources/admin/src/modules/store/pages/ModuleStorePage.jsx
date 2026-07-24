import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Drawer from 'antd/es/drawer';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;
const ModuleStoreTable = lazy(() => import('../components/ModuleStoreTable'));
const ModuleLifecycleActionPanel = lazy(() => import('../components/ModuleLifecycleActionPanel'));
const ModuleUpgradeChangelogModal = lazy(() => import('../components/ModuleUpgradeChangelogModal'));

export default function ModuleStorePage({ modules, onAction, permissions }) {
    const canUpgrade = permissions?.upgrade ?? false;
    const [selectedModuleKey, setSelectedModuleKey] = useState(null);
    const [changelogModuleKey, setChangelogModuleKey] = useState(null);

    useEffect(() => {
        if (selectedModuleKey && !modules?.some((moduleCard) => moduleCard.key === selectedModuleKey)) {
            setSelectedModuleKey(null);
        }
    }, [modules, selectedModuleKey]);

    const selectedModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === selectedModuleKey) ?? null, [modules, selectedModuleKey]);
    const changelogModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === changelogModuleKey) ?? null, [modules, changelogModuleKey]);

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
                    onOpenDetails={(moduleCard) => setSelectedModuleKey(moduleCard.key)}
                    onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                />
            </Suspense>

            <Drawer
                open={Boolean(selectedModuleKey)}
                onClose={() => setSelectedModuleKey(null)}
                width="min(760px, 96vw)"
                title={selectedModule ? `Chi tiết App: ${selectedModule.name}` : 'Chi tiết App'}
                className="module-detail-drawer"
                destroyOnHidden
            >
                {selectedModule ? (
                    <Suspense fallback={<Card loading title="Quản lý App" />}>
                        <ModuleLifecycleActionPanel
                            moduleCard={selectedModule}
                            permissions={permissions}
                            onAction={onAction}
                            onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                        />
                    </Suspense>
                ) : null}
            </Drawer>

            {changelogModuleKey ? (
                <Suspense fallback={null}>
                    <ModuleUpgradeChangelogModal
                        open={Boolean(changelogModuleKey)}
                        moduleCard={changelogModule}
                        canUpgrade={canUpgrade}
                        onCancel={() => setChangelogModuleKey(null)}
                        onAction={async (...args) => {
                            await onAction?.(...args);
                            setChangelogModuleKey(null);
                        }}
                    />
                </Suspense>
            ) : null}
        </Card>
    );
}
