import { Suspense, lazy, useEffect, useMemo, useState } from 'react';
import Card from 'antd/es/card';
import Col from 'antd/es/col';
import Row from 'antd/es/row';
import Space from 'antd/es/space';
import Typography from 'antd/es/typography';

const { Paragraph, Text } = Typography;
const ModuleStoreTable = lazy(() => import('../components/ModuleStoreTable'));
const ModuleLifecycleActionPanel = lazy(() => import('../components/ModuleLifecycleActionPanel'));
const ModuleUpgradeChangelogModal = lazy(() => import('../components/ModuleUpgradeChangelogModal'));

export default function ModuleStorePage({ modules, onAction, permissions }) {
    const canUpgrade = permissions?.upgrade ?? false;
    const [selectedModuleKey, setSelectedModuleKey] = useState(modules?.[0]?.key ?? null);
    const [changelogModuleKey, setChangelogModuleKey] = useState(null);

    useEffect(() => {
        if (!modules?.length) {
            setSelectedModuleKey(null);
            return;
        }

        if (!modules.some((moduleCard) => moduleCard.key === selectedModuleKey)) {
            setSelectedModuleKey(modules[0].key);
        }
    }, [modules, selectedModuleKey]);

    const selectedModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === selectedModuleKey) ?? null, [modules, selectedModuleKey]);
    const changelogModule = useMemo(() => modules.find((moduleCard) => moduleCard.key === changelogModuleKey) ?? null, [modules, changelogModuleKey]);

    return (
        <Card title="Module Store Flow">
            <Space direction="vertical" size={4} style={{ marginBottom: 16 }}>
                <Text className="card-label">Module Lifecycle</Text>
                <Paragraph style={{ marginBottom: 0 }}>
                    Tach rieng danh sach module, panel hanh dong lifecycle va changelog/upgrade modal de shell admin chi tai phan can dung.
                </Paragraph>
            </Space>
            <Row gutter={[16, 16]}>
                <Col xs={24} xl={15}>
                    <Suspense fallback={<Card loading title="Module Table" />}>
                        <ModuleStoreTable
                            modules={modules}
                            selectedModuleKey={selectedModuleKey}
                            onSelectModule={setSelectedModuleKey}
                            onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                        />
                    </Suspense>
                </Col>

                <Col xs={24} xl={9}>
                    <Suspense fallback={<Card loading title="Module Lifecycle" />}>
                        <ModuleLifecycleActionPanel
                            moduleCard={selectedModule}
                            permissions={permissions}
                            onAction={onAction}
                            onOpenChangelog={(moduleCard) => setChangelogModuleKey(moduleCard.key)}
                        />
                    </Suspense>
                </Col>
            </Row>

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
